<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Entity\TranslatableRevisionableStorageInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\file\FileInterface;
use Drupal\hel_tpm_file_garbage_collector\Event\FileGarbageCollectorEvents;
use Drupal\hel_tpm_file_garbage_collector\Event\FilesInUseEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Class responsible for managing the garbage collection of unused files.
 *
 * A file is considered garbage only when every usage recorded in the
 * file_usage table comes from a file or image field of a revisionable content
 * entity, and the file is referenced solely by revisions of that entity that
 * are:
 * - not the default revision,
 * - not the latest (translation affected) revision, and
 * - replaced by a newer revision longer ago than the configured time limit,
 *   i.e. the file was removed from the content more than the time limit ago.
 *
 * Any usage the collector cannot evaluate (unknown modules, entity types or
 * storage, fields not holding the file) marks the file as active, so that
 * files are never deleted because of missing information.
 */
final class FileGarbageCollector {

  /**
   * Represents a time limit duration for a specific operation or configuration.
   *
   * @var string
   */
  public static $timeLimit = '-6 months';

  /**
   * Queue identifier for the file garbage worker process.
   *
   * @var string
   */
  public static $queue = 'hel_tpm_file_garbage_collector_file_garbage_worker';

  /**
   * Maximum depth used when resolving composite entity hosts.
   *
   * @var int
   */
  protected const MAX_PARENT_DEPTH = 5;

  /**
   * Number of files evaluated per batch.
   *
   * @var int
   */
  public const BATCH_SIZE = 100;

  /**
   * Default and latest revision IDs, keyed by entity type and ID.
   *
   * Reset for every file so that each evaluation uses current data.
   *
   * @var array
   */
  private array $protectedRevisions = [];

  /**
   * Evaluation reason: the file has no usage, core handles it.
   */
  public const REASON_NO_USAGE = 'no_usage';

  /**
   * Evaluation reason: usage registered by another module than file fields.
   */
  public const REASON_OTHER_MODULE = 'other_module';

  /**
   * Evaluation reason: the entity type cannot be evaluated.
   */
  public const REASON_UNSUPPORTED = 'unsupported_entity_type';

  /**
   * Evaluation reason: no file or image field of the entity has the file.
   */
  public const REASON_NOT_REFERENCED = 'not_referenced';

  /**
   * Evaluation reason: the default or a latest revision has the file.
   */
  public const REASON_CURRENT_REVISION = 'current_revision';

  /**
   * Evaluation reason: the file was used within the time limit.
   */
  public const REASON_RECENTLY_USED = 'recently_used';

  /**
   * Evaluation reason: the host of a composite entity cannot be resolved.
   */
  public const REASON_UNRESOLVED_HOST = 'unresolved_host';

  /**
   * Evaluation reason: the file is unused and can be deleted.
   */
  public const REASON_UNUSED = 'unused';

  /**
   * Constructs a FileGarbageCollector object.
   */
  public function __construct(
    private readonly Connection $connection,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly QueueFactory $queueFactory,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * Evaluates all used files and queues files that are no longer active.
   *
   * Processes every file in one call. Cron uses collectBatch() instead to
   * spread the work over several runs.
   *
   * @return void
   *   Returns nothing.
   */
  public function collect(): void {
    $cursor = 0;
    do {
      $cursor = $this->collectBatch($cursor, static::BATCH_SIZE);
    } while ($cursor !== NULL);
  }

  /**
   * Evaluates a batch of used files and queues files that are not active.
   *
   * @param int $after
   *   Evaluate files with an ID greater than this.
   * @param int $limit
   *   Maximum number of files to evaluate.
   *
   * @return int|null
   *   The last evaluated file ID to continue from, or NULL when all files
   *   have been evaluated.
   */
  public function collectBatch(int $after, int $limit): ?int {
    $queue = $this->getQueue();

    $fids = $this->connection->select('file_usage', 'f')
      ->fields('f', ['fid'])
      ->condition('f.fid', $after, '>')
      ->distinct()
      ->orderBy('f.fid')
      ->range(0, $limit)
      ->execute()
      ->fetchCol();

    $active = [];
    foreach ($fids as $fid) {
      if ($this->isFileActive((int) $fid)) {
        $active[] = (int) $fid;
      }
      else {
        $queue->createItem(['fid' => (int) $fid]);
      }
    }
    if ($active) {
      $this->eventDispatcher->dispatch(new FilesInUseEvent($active), FileGarbageCollectorEvents::FILES_IN_USE);
    }

    return count($fids) < $limit ? NULL : (int) end($fids);
  }

  /**
   * Checks whether a file is still in active use.
   *
   * @param int $fid
   *   The file ID.
   *
   * @return bool
   *   TRUE if the file must be kept, FALSE if it can be deleted.
   */
  public function isFileActive(int $fid): bool {
    return $this->evaluateFile($fid)['active'];
  }

  /**
   * Evaluates the usages of a file.
   *
   * @param int $fid
   *   The file ID.
   * @param bool $all
   *   (optional) Evaluate every usage, instead of stopping at the first
   *   active one.
   *
   * @return array
   *   An array with:
   *   - active: TRUE if the file must be kept.
   *   - reason: One of the REASON_* constants: the reason of the first active
   *     usage, REASON_NO_USAGE or REASON_UNUSED.
   *   - usages: The evaluated usages, see evaluateUsage(). When the file is
   *     not active or $all is TRUE, every usage is included.
   */
  public function evaluateFile(int $fid, bool $all = FALSE): array {
    $this->protectedRevisions = [];
    $usages = $this->getFileUsages($fid);

    // Files without usage are handled by core's temporary file cleanup.
    if (empty($usages)) {
      return ['active' => TRUE, 'reason' => self::REASON_NO_USAGE, 'usages' => []];
    }

    $evaluation = ['active' => FALSE, 'reason' => self::REASON_UNUSED, 'usages' => []];
    foreach ($usages as $usage) {
      $result = $this->evaluateUsage($fid, $usage);
      $evaluation['usages'][] = $result;
      if ($result['active'] && !$evaluation['active']) {
        $evaluation['active'] = TRUE;
        $evaluation['reason'] = $result['reason'];
        if (!$all) {
          break;
        }
      }
    }
    return $evaluation;
  }

  /**
   * Deletes an unused file and removes it from old revisions.
   *
   * References in old revisions are removed so that reverting to or viewing
   * an old revision does not point to a missing file. Default and latest
   * revisions are never modified.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file to delete.
   *
   * @return int
   *   The number of revision field values removed.
   *
   * @throws \LogicException
   *   When the file is still in active use.
   */
  public function deleteFile(FileInterface $file): int {
    $fid = (int) $file->id();
    if ($this->isFileActive($fid)) {
      throw new \LogicException(sprintf('File %d is still in use.', $fid));
    }

    $transaction = $this->connection->startTransaction();
    try {
      $removed = $this->removeRevisionReferences($fid);
      $file->delete();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
    return $removed;
  }

  /**
   * Removes file references from old entity revisions.
   *
   * @param int $fid
   *   The file ID.
   *
   * @return int
   *   The number of revision field values removed.
   */
  protected function removeRevisionReferences(int $fid): int {
    $this->protectedRevisions = [];
    $removed = 0;

    foreach ($this->getFileUsages($fid) as $usage) {
      if ($usage['module'] !== 'file' || !$this->isSupportedEntityType($usage['type'])) {
        continue;
      }
      $entity_type_id = $usage['type'];
      $entity_id = $usage['id'];
      /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity_type = $storage->getEntityType();
      /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
      $table_mapping = $storage->getTableMapping();

      foreach ($this->getFileFieldDefinitions($entity_type_id) as $definition) {
        $revisions = array_filter(
          $this->getReferencingRevisions($entity_type_id, $entity_id, $definition, 'target_id', [$fid]),
          fn (array $revision) => !$this->isProtectedRevision($entity_type_id, $entity_id, $revision),
        );
        if (empty($revisions)) {
          continue;
        }
        $revision_ids = array_column($revisions, 'revision_id');
        $column = $table_mapping->getFieldColumnName($definition, 'target_id');

        if ($table_mapping->requiresDedicatedTableStorage($definition)) {
          $removed += $this->connection->delete($table_mapping->getDedicatedRevisionTableName($definition))
            ->condition('entity_id', $entity_id)
            ->condition('revision_id', $revision_ids, 'IN')
            ->condition($column, $fid)
            ->execute();
        }
        else {
          // Base fields share the revision table row with other fields, so
          // only the field columns are cleared.
          $fields = array_fill_keys(array_values($table_mapping->getColumnNames($definition->getName())), NULL);
          $removed += $this->connection->update($this->getSharedRevisionTable($storage))
            ->fields($fields)
            ->condition($entity_type->getKey('id'), $entity_id)
            ->condition($entity_type->getKey('revision'), $revision_ids, 'IN')
            ->condition($column, $fid)
            ->execute();
        }
      }
      $storage->resetCache([$entity_id]);
    }
    return $removed;
  }

  /**
   * Gets the file_usage rows of a file.
   *
   * @param int $fid
   *   The file ID.
   *
   * @return array
   *   The file_usage rows.
   */
  protected function getFileUsages(int $fid): array {
    return $this->connection->select('file_usage', 'f')
      ->fields('f')
      ->condition('f.fid', $fid)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Retrieves the queue instance.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue instance associated with the current factory.
   */
  public function getQueue(): QueueInterface {
    return $this->queueFactory->get($this::$queue);
  }

  /**
   * Evaluates a single file_usage row.
   *
   * @param int $fid
   *   The file ID.
   * @param array $usage
   *   The file_usage row.
   *
   * @return array
   *   An array with:
   *   - module, entity_type, entity_id, count: From the file_usage row.
   *   - active: TRUE if the usage is active or cannot be evaluated.
   *   - reason: One of the REASON_* constants.
   *   - revision_ids: Revision IDs referencing the file.
   *   - last_used: Timestamp when the file was last in use, if known.
   *   - host: For composite entities, the host entity with 'entity_type',
   *     'entity_id', 'revision_ids' and a nested 'host', otherwise NULL.
   */
  protected function evaluateUsage(int $fid, array $usage): array {
    $result = [
      'module' => $usage['module'],
      'entity_type' => $usage['type'],
      'entity_id' => $usage['id'],
      'count' => (int) $usage['count'],
      'revision_ids' => [],
    ];

    // Only usages registered by file and image fields can be evaluated.
    // Other modules (e.g. editor inline images) track usage on their own.
    if ($usage['module'] !== 'file') {
      return $result + $this->active(self::REASON_OTHER_MODULE);
    }
    if (!$this->isSupportedEntityType($usage['type'])) {
      return $result + $this->active(self::REASON_UNSUPPORTED);
    }

    $revisions = [];
    foreach ($this->getFileFieldDefinitions($usage['type']) as $definition) {
      $revisions = array_merge($revisions, $this->getReferencingRevisions($usage['type'], $usage['id'], $definition, 'target_id', [$fid]));
    }
    $result['revision_ids'] = $this->getRevisionIds($revisions);

    // The file is not referenced from any known field of the entity, so the
    // usage comes from somewhere the collector does not understand.
    if (empty($revisions)) {
      return $result + $this->active(self::REASON_NOT_REFERENCED);
    }

    return $result + $this->evaluateRevisions($usage['type'], $usage['id'], $revisions);
  }

  /**
   * Builds the evaluation result of a usage that keeps the file.
   *
   * @param string $reason
   *   One of the REASON_* constants.
   *
   * @return array
   *   The evaluation result, see evaluateRevisions().
   */
  protected function active(string $reason): array {
    return ['active' => TRUE, 'reason' => $reason, 'last_used' => NULL, 'host' => NULL];
  }

  /**
   * Gets the unique, sorted revision IDs of a list of revisions.
   *
   * @param array $revisions
   *   List of revisions, each an array with 'revision_id' and 'langcode'.
   *
   * @return int[]
   *   The revision IDs.
   */
  protected function getRevisionIds(array $revisions): array {
    $revision_ids = array_unique(array_map('intval', array_column($revisions, 'revision_id')));
    sort($revision_ids);
    return $revision_ids;
  }

  /**
   * Checks whether the entity type can be evaluated by the collector.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return bool
   *   TRUE for revisionable content entities using SQL storage.
   */
  protected function isSupportedEntityType(string $entity_type_id): bool {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
    if (!$entity_type instanceof ContentEntityTypeInterface || !$entity_type->isRevisionable()) {
      return FALSE;
    }
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    return $storage instanceof SqlEntityStorageInterface
      && $storage instanceof RevisionableStorageInterface
      && $storage->getTableMapping() instanceof DefaultTableMapping;
  }

  /**
   * Evaluates whether any of the given entity revisions is still active.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int|string $entity_id
   *   The entity ID.
   * @param array $revisions
   *   List of revisions, each an array with 'revision_id' and 'langcode'.
   * @param int $depth
   *   (optional) Current composite parent resolution depth.
   *
   * @return array
   *   An array with:
   *   - active: TRUE if at least one revision is active or activity cannot be
   *     resolved.
   *   - reason: One of the REASON_* constants.
   *   - last_used: Timestamp when the file was last in use, if known.
   *   - host: The host entity of composite entities, see evaluateUsage().
   */
  protected function evaluateRevisions(string $entity_type_id, int|string $entity_id, array $revisions, int $depth = 0): array {
    if (!$this->isSupportedEntityType($entity_type_id)) {
      return $this->active(self::REASON_UNSUPPORTED);
    }

    // Default and latest revisions.
    foreach ($revisions as $revision) {
      if ($this->isProtectedRevision($entity_type_id, $entity_id, $revision)) {
        return $this->active(self::REASON_CURRENT_REVISION);
      }
    }

    // Revisions in use within the time limit.
    $last_used = $this->getLastUsedTime($entity_type_id, $entity_id, $revisions);
    if ($last_used !== NULL) {
      $active = $last_used > $this->getTimeLimit();
      return [
        'active' => $active,
        'reason' => $active ? self::REASON_RECENTLY_USED : self::REASON_UNUSED,
        'last_used' => $last_used ?: NULL,
        'host' => NULL,
      ];
    }

    // Composite entities (e.g. paragraphs) without timestamps inherit the
    // activity of the host revisions referencing them.
    return $this->evaluateHostRevisions($entity_type_id, $entity_id, $this->getRevisionIds($revisions), $depth);
  }

  /**
   * Checks whether a revision is the default or a latest revision.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int|string $entity_id
   *   The entity ID.
   * @param array $revision
   *   The revision, an array with 'revision_id' and 'langcode'.
   *
   * @return bool
   *   TRUE if the revision is the default revision, the latest revision or
   *   the latest translation affected revision of its language.
   */
  protected function isProtectedRevision(string $entity_type_id, int|string $entity_id, array $revision): bool {
    $key = $entity_type_id . ':' . $entity_id;
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity_type_id);

    if (!isset($this->protectedRevisions[$key])) {
      $default = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition($storage->getEntityType()->getKey('id'), $entity_id)
        ->execute();
      $this->protectedRevisions[$key] = [
        'default' => array_map('intval', array_keys($default)),
        'latest' => (int) $storage->getLatestRevisionId($entity_id),
        'translations' => [],
      ];
    }
    $protected = &$this->protectedRevisions[$key];

    $revision_id = (int) $revision['revision_id'];
    if (in_array($revision_id, $protected['default'], TRUE) || $protected['latest'] === $revision_id) {
      return TRUE;
    }

    $langcode = $revision['langcode'];
    if ($langcode === NULL || !$storage instanceof TranslatableRevisionableStorageInterface) {
      return FALSE;
    }
    if (!isset($protected['translations'][$langcode])) {
      $protected['translations'][$langcode] = (int) $storage->getLatestTranslationAffectedRevisionId($entity_id, $langcode);
    }
    return $protected['translations'][$langcode] === $revision_id;
  }

  /**
   * Gets the time when the revisions last used the file.
   *
   * A file referenced by a revision is considered in use until the next
   * revision of the same translation replaced it. The time limit is thus
   * counted from the moment the file was removed, not from the moment the
   * referencing revision was created.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int|string $entity_id
   *   The entity ID.
   * @param array $revisions
   *   List of revisions, each an array with 'revision_id' and 'langcode'.
   *
   * @return int|null
   *   The timestamp, 0 if no timestamp was found, or NULL when the entity type
   *   has no timestamp to use.
   */
  protected function getLastUsedTime(string $entity_type_id, int|string $entity_id, array $revisions): ?int {
    /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_type = $storage->getEntityType();
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = $storage->getTableMapping();
    $definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
    $revision_key = $entity_type->getKey('revision');
    $langcode_key = $entity_type->getKey('langcode');

    $changed_key = $this->getChangedKey($entity_type_id);
    $revision_created_key = $entity_type->getRevisionMetadataKey('revision_created');

    if ($changed_key && isset($definitions[$changed_key]) && !$table_mapping->requiresDedicatedTableStorage($definitions[$changed_key])) {
      $timestamp_definition = $definitions[$changed_key];
      $table = $this->getSharedRevisionTable($storage);
      $per_translation = $entity_type->isTranslatable() && $langcode_key;
    }
    elseif ($revision_created_key && isset($definitions[$revision_created_key])) {
      $timestamp_definition = $definitions[$revision_created_key];
      $table = $table_mapping->getRevisionTable();
      $per_translation = FALSE;
    }
    else {
      return NULL;
    }

    $timestamp_column = $table_mapping->getFieldColumnName($timestamp_definition, 'value');
    $rta_key = $entity_type->getKey('revision_translation_affected');
    $filter_rta = $per_translation && $rta_key && isset($definitions[$rta_key]);
    $last_used = 0;

    // Referencing revisions.
    $query = $this->connection->select($table, 'r')
      ->fields('r', [$revision_key])
      ->condition('r.' . $revision_key, array_column($revisions, 'revision_id'), 'IN');
    $query->addField('r', $timestamp_column, 'timestamp');
    if ($per_translation) {
      $query->addField('r', $langcode_key, 'langcode');
    }
    if ($filter_rta) {
      $query->condition('r.' . $rta_key, 1);
    }

    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      foreach ($revisions as $revision) {
        if ((int) $row[$revision_key] !== (int) $revision['revision_id']) {
          continue;
        }
        if (!$per_translation || $revision['langcode'] === NULL || $revision['langcode'] === $row['langcode']) {
          $last_used = max($last_used, (int) $row['timestamp']);
        }
      }
    }

    // Revisions which removed the file: the first revision after the last
    // referencing revision of each translation.
    $last_references = [];
    foreach ($revisions as $revision) {
      $group = $per_translation ? (string) $revision['langcode'] : '';
      $last_references[$group] = max($last_references[$group] ?? 0, (int) $revision['revision_id']);
    }
    foreach ($last_references as $langcode => $last_reference) {
      $query = $this->connection->select($table, 'r')
        ->condition('r.' . $entity_type->getKey('id'), $entity_id)
        ->condition('r.' . $revision_key, $last_reference, '>')
        ->orderBy('r.' . $revision_key)
        ->range(0, 1);
      $query->addField('r', $timestamp_column, 'timestamp');
      if ($per_translation && $langcode !== '') {
        $query->condition('r.' . $langcode_key, $langcode);
      }
      if ($filter_rta) {
        $query->condition('r.' . $rta_key, 1);
      }
      $removed = $query->execute()->fetchField();
      if ($removed !== FALSE) {
        $last_used = max($last_used, (int) $removed);
      }
    }
    return $last_used;
  }

  /**
   * Resolves activity of composite entity revisions through their host.
   *
   * @param string $entity_type_id
   *   The composite entity type ID.
   * @param int|string $entity_id
   *   The composite entity ID.
   * @param int[] $revision_ids
   *   The composite revision IDs referencing the file.
   * @param int $depth
   *   Current parent resolution depth.
   *
   * @return array
   *   The evaluation of the host revisions, see evaluateRevisions(), with the
   *   host entity in 'host'.
   */
  protected function evaluateHostRevisions(string $entity_type_id, int|string $entity_id, array $revision_ids, int $depth): array {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $parent_type_field = $entity_type->get('entity_revision_parent_type_field');
    $parent_id_field = $entity_type->get('entity_revision_parent_id_field');
    $parent_field_name_field = $entity_type->get('entity_revision_parent_field_name_field');
    if (!$parent_type_field || !$parent_id_field || !$parent_field_name_field || $depth >= static::MAX_PARENT_DEPTH) {
      return $this->active(self::REASON_UNRESOLVED_HOST);
    }

    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    if (!$entity) {
      return $this->active(self::REASON_UNRESOLVED_HOST);
    }
    $parent_type = $entity->get($parent_type_field)->value;
    $parent_id = $entity->get($parent_id_field)->value;
    $parent_field_name = $entity->get($parent_field_name_field)->value;
    if (!$parent_type || !$parent_id || !$parent_field_name || !$this->isSupportedEntityType($parent_type)) {
      return $this->active(self::REASON_UNRESOLVED_HOST);
    }

    $parent_definitions = $this->entityFieldManager->getFieldStorageDefinitions($parent_type);
    if (!isset($parent_definitions[$parent_field_name])) {
      return $this->active(self::REASON_UNRESOLVED_HOST);
    }

    $host_revisions = $this->getReferencingRevisions($parent_type, $parent_id, $parent_definitions[$parent_field_name], 'target_revision_id', $revision_ids);
    // Be conservative if no host revision references the composite revisions.
    if (empty($host_revisions)) {
      return $this->active(self::REASON_UNRESOLVED_HOST);
    }

    $host = $this->evaluateRevisions($parent_type, $parent_id, $host_revisions, $depth + 1);
    return [
      'active' => $host['active'],
      'reason' => $host['reason'],
      'last_used' => $host['last_used'],
      'host' => [
        'entity_type' => $parent_type,
        'entity_id' => $parent_id,
        'revision_ids' => $this->getRevisionIds($host_revisions),
        'host' => $host['host'],
      ],
    ];
  }

  /**
   * Finds entity revisions where a field property matches the given values.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int|string $entity_id
   *   The entity ID.
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $definition
   *   The field storage definition.
   * @param string $property
   *   The field property, e.g. 'target_id'.
   * @param array $values
   *   The values to match.
   *
   * @return array
   *   List of revisions, each an array with 'revision_id' and 'langcode'.
   */
  protected function getReferencingRevisions(string $entity_type_id, int|string $entity_id, FieldStorageDefinitionInterface $definition, string $property, array $values): array {
    /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_type = $storage->getEntityType();
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = $storage->getTableMapping();
    $column = $table_mapping->getFieldColumnName($definition, $property);

    if ($table_mapping->requiresDedicatedTableStorage($definition)) {
      if (!$definition->isRevisionable()) {
        return [];
      }
      $query = $this->connection->select($table_mapping->getDedicatedRevisionTableName($definition), 't');
      $query->addField('t', 'revision_id', 'revision_id');
      $query->addField('t', 'langcode', 'langcode');
      $query->condition('t.entity_id', $entity_id);
    }
    else {
      $query = $this->connection->select($this->getSharedRevisionTable($storage), 't');
      $query->addField('t', $entity_type->getKey('revision'), 'revision_id');
      if ($langcode_key = $entity_type->getKey('langcode')) {
        $query->addField('t', $langcode_key, 'langcode');
      }
      $query->condition('t.' . $entity_type->getKey('id'), $entity_id);
    }

    $rows = $query->condition('t.' . $column, $values, 'IN')
      ->distinct()
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(static fn (array $row) => [
      'revision_id' => $row['revision_id'],
      'langcode' => $row['langcode'] ?? NULL,
    ], $rows);
  }

  /**
   * Gets the revision table holding shared (base) field values.
   *
   * @param \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage
   *   The entity storage.
   *
   * @return string
   *   The revision data table for translatable entities, otherwise the
   *   revision table.
   */
  protected function getSharedRevisionTable(SqlEntityStorageInterface $storage): string {
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = $storage->getTableMapping();
    return $table_mapping->getRevisionDataTable() ?: $table_mapping->getRevisionTable();
  }

  /**
   * Retrieves the key for the 'changed' field of a given entity type.
   *
   * @param string $entity_type_id
   *   The ID of the entity type for which to retrieve the 'changed' field key.
   *
   * @return string|null
   *   The key of the 'changed' field if found, or NULL if no such field exists.
   */
  protected function getChangedKey(string $entity_type_id): ?string {
    $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
    foreach ($field_storage_definitions as $key => $definition) {
      if ($definition->getType() === 'changed') {
        return $key;
      }
    }
    return NULL;
  }

  /**
   * Retrieves the timestamp for the configured time limit.
   *
   * @return int
   *   The timestamp corresponding to the configured time limit.
   */
  protected function getTimeLimit(): int {
    $date = new DrupalDateTime($this::$timeLimit);
    return $date->getTimestamp();
  }

  /**
   * Retrieves file and image field storage definitions for an entity type.
   *
   * Includes both configurable and base fields (e.g. media thumbnails).
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface[]
   *   File and image field storage definitions, keyed by field name.
   */
  protected function getFileFieldDefinitions(string $entity_type_id): array {
    return array_filter(
      $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id),
      static fn (FieldStorageDefinitionInterface $definition) => !$definition->hasCustomStorage()
        && in_array($definition->getType(), ['file', 'image'], TRUE),
    );
  }

}
