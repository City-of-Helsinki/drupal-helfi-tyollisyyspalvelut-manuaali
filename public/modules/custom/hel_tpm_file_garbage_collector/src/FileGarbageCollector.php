<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Class responsible for managing the garbage collection of unused files.
 *
 * This class provides functionality to analyze file usage data,
 * determine whether files are active or inactive, and queue inactive
 * files for cleanup. It utilizes database queries, entity type
 * management, and queue management functionalities to accomplish its tasks.
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
   * Constructs a FileGarbageCollector object.
   */
  public function __construct(
    private readonly Connection $connection,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly QueueFactory $queueFactory,
  ) {}

  /**
   * Collects file usage information and queues files that are no longer active.
   *
   * @return void
   *   Returns nothing.
   */
  public function collect(): void {
    $queue = $this->getQueue();

    $file_usage = $this->connection->select('file_usage', 'f')
      ->fields('f')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $file_usage_array = [];
    foreach ($file_usage as $row) {
      $row['tables'] = $this->getFileTables($row);
      $file_usage_array[$row['fid']][] = $row;
    }

    foreach ($file_usage_array as $fid => $file_usage) {
      if (!$this->isActiveFile($file_usage)) {
        $queue->createItem(['fid' => $fid]);
      }
    }
  }

  /**
   * Retrieves the queue instance.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue instance associated with the current factory.
   */
  protected function getQueue() {
    return $this->queueFactory->get($this::$queue);
  }

  /**
   * Checks if there is an active file in the file usage array.
   *
   * @param array $file_usage
   *   An array of files to check.
   *
   * @return bool
   *   TRUE if at least one file is active, FALSE otherwise.
   */
  protected function isActiveFile($file_usage) {
    foreach ($file_usage as $file) {
      if ($this->isActive($file)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks if the provided file is active based on its usage.
   *
   * @param array $file
   *   An associative array representing the file, containing details
   *   such as tables, entity type information, and file ID (fid).
   *
   * @return bool
   *   TRUE if the file is actively in use; otherwise, FALSE.
   */
  protected function isActive($file) {

    // If file is in default revision file is active.
    if ($this->isFileInDefaultRevision($file)) {
      return TRUE;
    }

    // If file is referenced in latest revision assume file is in active use.
    if ($this->isFileInLatestRevision($file)) {
      return TRUE;
    }

    foreach ($file['tables'] as $field_name => $table_info) {
      $col = $field_name . '_target_id';
      $entity_type_data = $table_info['entity_type'];
      $query = $this->connection->select($entity_type_data['revision_table'], 'dt')
        ->fields('dt')
        ->condition('dt.revision_translation_affected', 1)
        ->condition('dt.' . $entity_type_data['changed_key'], $this->getTimeLimit(), '>');
      $query->join($table_info['field_revision_table'], 'frt',
        'dt.' . $entity_type_data['revision_key'] . '= frt.revision_id AND dt.langcode = frt.langcode'
      );
      $usage = $query
        ->fields('frt')
        ->condition('frt.' . $col, $file['fid'])
        ->countQuery()
        ->execute()->fetchField();
      if ($usage > 0) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Determines whether a file exists in its default revision.
   *
   * @param array $file
   *   An associative array representing the file, which includes its related
   *   tables and file ID (fid).
   *
   * @return bool
   *   TRUE if the file is present in its default revision, FALSE otherwise.
   */
  protected function isFileInDefaultRevision($file) {
    foreach ($file['tables'] as $field => $table_data) {
      $field_key = sprintf('%s_target_id', $field);
      $count = $this->connection->select($table_data['field_entity_table'])
        ->condition($field_key, $file['fid'])
        ->countQuery()
        ->execute()
        ->fetchField();
      if ($count > 0) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Determines if the given file exists in the latest entity revision.
   *
   * @param array $file
   *   An associative array representing the file, which includes:
   *   - 'tables': An array of table information related to the file.
   *   - 'fid': The file ID to check for its presence in the latest revision.
   *
   * @return bool
   *   TRUE if the file exists in the latest revision of the associated entity,
   *   FALSE otherwise.
   */
  protected function isFileInLatestRevision($file) {
    foreach ($file['tables'] as $field_name => $table_info) {
      $entity_type_data = $table_info['entity_type'];
      $col = $field_name . '_target_id';

      // Fetch latest revisions per langcode.
      $latest_revisions = $this->getLatestRevisions($entity_type_data['revision_table'], $entity_type_data['revision_key']);
      if (empty($latest_revisions)) {
        return FALSE;
      }
      // Check if file is in use in any latest revision.
      foreach ($latest_revisions as $revision) {
        $file_usage = $this->getRevisionFileReferences($table_info['field_revision_table'], $revision->{$entity_type_data['revision_key']}, $revision->langcode);
        foreach ($file_usage as $row) {
          if ($row[$col] === $file['fid']) {
            return TRUE;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Retrieves file references for a specific revision in a given language.
   *
   * @param string $field_table
   *   The name of the database table containing the field data.
   * @param int $vid
   *   The revision ID for which file references are to be retrieved.
   * @param string $langcode
   *   The language code to filter file references.
   *
   * @return array
   *   An associative array of file references retrieved from the database.
   */
  protected function getRevisionFileReferences($field_table, $vid, $langcode) {
    return $this->connection->select($field_table, 'ft')
      ->fields('ft')
      ->condition('ft.langcode', $langcode)
      ->condition('ft.revision_id', $vid)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Retrieves the latest revisions for a specified revision table.
   *
   * @param string $revision_table
   *   The name of the revision table to query.
   * @param string $revision_key
   *   The key used to order the revisions.
   *
   * @return array
   *   An associative array of the latest revisions, indexed by language code.
   */
  protected function getLatestRevisions($revision_table, $revision_key) {
    return $this->connection->select($revision_table, 'dt')
      ->fields('dt')
      ->condition('dt.revision_translation_affected', 1)
      ->orderBy($revision_key, 'ASC')
      ->execute()
      ->fetchAllAssoc('langcode');
  }

  /**
   * Retrieves the file tables and related metadata for a given file type.
   *
   * @param array $file
   *   An associative array containing file data. Must include a 'type' key
   *   that specifies the file type.
   *
   * @return array
   *   An associative array of file tables. Each key represents a field name
   *   and its value is an array containing:
   *   - 'entity_type': Information related to the entity type, including:
   *       - 'revision_table': The name of the revision data table.
   *       - 'revision_key': The revision key for the entity type.
   *       - 'changed_key': The changed timestamp key for the entity type.
   *   - 'field_revision_table': The name of the dedicated revision table
   *     for the field.
   */
  protected function getFileTables($file) {
    $fields = $this->entityTypeFileFields($file['type']);
    $storage = $this->entityTypeManager->getStorage($file['type']);
    $entity_type = $storage->getEntityType();
    $table_mapping = $storage->getTableMapping();
    $file_tables = [];
    $changed_key = $this->getChangedKey($file['type']);
    foreach ($fields as $field) {
      $file_tables[$field->getName()] = [
        'entity_type' => [
          'revision_table' => $entity_type->getRevisionDataTable(),
          'revision_key' => $entity_type->getKey('revision'),
          'changed_key' => $changed_key,
        ],
        'field_entity_table' => $table_mapping->getDedicatedDataTableName($field),
        'field_revision_table' => $table_mapping->getDedicatedRevisionTableName($field),
      ];
    }
    return $file_tables;
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
  protected function getChangedKey($entity_type_id) {
    $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
    foreach ($field_storage_definitions as $key => $definition) {
      if ($definition->getType() === 'changed') {
        return $key;
      }
    }
  }

  /**
   * Retrieves the timestamp for the configured time limit.
   *
   * @return int
   *   The timestamp corresponding to the configured time limit.
   */
  protected function getTimeLimit() {
    $date = new DrupalDateTime($this::$timeLimit);
    return $date->getTimestamp();
  }

  /**
   * Retrieves file fields for the specified entity type.
   *
   * @param string $entity_type
   *   The entity type for which file fields are to be retrieved.
   *
   * @return array
   *   An array of field storage configurations for file fields associated
   *   with the specified entity type.
   */
  public function entityTypeFileFields($entity_type) {
    $fields = $this->entityTypeManager->getStorage('field_storage_config')
      ->loadByProperties([
        'type' => 'file',
        'entity_type' => $entity_type,
      ]);
    return $fields;
  }

}
