<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector_report;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;

/**
 * Stores a report of files deleted or selected for deletion.
 *
 * Each file has a single report entry. In dry run mode the entry lists the
 * file as a deletion candidate, and it is removed again if the file is taken
 * back into use. When the file is deleted, the entry is kept permanently with
 * the status 'deleted'.
 */
final class FileGarbageReport {

  /**
   * The report database table.
   */
  public const TABLE = 'hel_tpm_file_garbage_collector_report';

  /**
   * Status of files that would be deleted in dry run mode.
   */
  public const STATUS_DRY_RUN = 'dry_run';

  /**
   * Status of deleted files.
   */
  public const STATUS_DELETED = 'deleted';

  /**
   * Cache tag invalidated when the report changes.
   */
  public const CACHE_TAG = 'hel_tpm_file_garbage_collector_report';

  /**
   * Constructs a FileGarbageReport object.
   */
  public function __construct(
    private readonly Connection $connection,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Gets the human readable status labels.
   *
   * Used as the Views options callback of the status field and filter.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   Status labels keyed by status.
   */
  public static function getStatusLabels(): array {
    return [
      self::STATUS_DRY_RUN => new TranslatableMarkup('Would be deleted (dry run)'),
      self::STATUS_DELETED => new TranslatableMarkup('Deleted'),
    ];
  }

  /**
   * Records a file in the report.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file. May already be deleted, only its values are used.
   * @param array $evaluation
   *   The evaluation of the file, see
   *   \Drupal\hel_tpm_file_garbage_collector\FileGarbageCollector::evaluateFile().
   * @param string $status
   *   One of the STATUS_* constants.
   * @param int|null $references_removed
   *   (optional) Number of revision field values removed with the file.
   */
  public function record(FileInterface $file, array $evaluation, string $status, ?int $references_removed = NULL): void {
    $usages = array_map($this->describeUsage(...), $evaluation['usages']);
    $last_used = array_filter(array_column($usages, 'last_used'));
    $now = $this->time->getCurrentTime();

    $fields = [
      'status' => $status,
      'uri' => $file->getFileUri(),
      'filename' => $file->getFilename(),
      'filemime' => $file->getMimeType(),
      'filesize' => $file->getSize(),
      'file_created' => $file->getCreatedTime(),
      'file_owner' => $file->getOwnerId(),
      'usages' => Json::encode($usages),
      'last_used' => $last_used ? max($last_used) : NULL,
      'references_removed' => $references_removed,
      'changed' => $now,
    ];
    $this->connection->merge(self::TABLE)
      ->key('fid', (int) $file->id())
      ->insertFields($fields + ['fid' => (int) $file->id(), 'created' => $now])
      ->updateFields($fields)
      ->execute();
    $this->cacheTagsInvalidator->invalidateTags([self::CACHE_TAG]);
  }

  /**
   * Removes dry run entries of files which are in use again.
   *
   * @param int[] $fids
   *   The file IDs.
   */
  public function removeCandidates(array $fids): void {
    if (empty($fids)) {
      return;
    }
    $removed = $this->connection->delete(self::TABLE)
      ->condition('fid', $fids, 'IN')
      ->condition('status', self::STATUS_DRY_RUN)
      ->execute();
    if ($removed) {
      $this->cacheTagsInvalidator->invalidateTags([self::CACHE_TAG]);
    }
  }

  /**
   * Gets report entries, most recently recorded first.
   *
   * @param string|null $status
   *   (optional) Only return entries with this status.
   * @param int $limit
   *   (optional) Maximum number of entries, 0 for all.
   * @param int $offset
   *   (optional) Number of entries to skip.
   *
   * @return array
   *   The report entries, with 'usages' decoded.
   */
  public function getEntries(?string $status = NULL, int $limit = 0, int $offset = 0): array {
    $query = $this->connection->select(self::TABLE, 'r')
      ->fields('r')
      ->orderBy('r.changed', 'DESC')
      ->orderBy('r.fid', 'DESC');
    if ($status !== NULL) {
      $query->condition('r.status', $status);
    }
    if ($limit > 0) {
      $query->range($offset, $limit);
    }

    $entries = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($entries as &$entry) {
      $entry['usages'] = Json::decode($entry['usages'] ?? '') ?: [];
    }
    return $entries;
  }

  /**
   * Gets the report entry of a file.
   *
   * @param int $fid
   *   The file ID.
   *
   * @return array|null
   *   The report entry with 'usages' decoded, or NULL if the file is not in
   *   the report.
   */
  public function getEntry(int $fid): ?array {
    $entry = $this->connection->select(self::TABLE, 'r')
      ->fields('r')
      ->condition('r.fid', $fid)
      ->execute()
      ->fetchAssoc();
    if (!$entry) {
      return NULL;
    }
    $entry['usages'] = Json::decode($entry['usages'] ?? '') ?: [];
    return $entry;
  }

  /**
   * Counts report entries.
   *
   * @param string|null $status
   *   (optional) Only count entries with this status.
   *
   * @return int
   *   The number of entries.
   */
  public function count(?string $status = NULL): int {
    $query = $this->connection->select(self::TABLE, 'r');
    if ($status !== NULL) {
      $query->condition('r.status', $status);
    }
    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * Adds entity details to an evaluated usage.
   *
   * Labels are stored so that the report stays readable after the entity or
   * its revisions change.
   *
   * @param array $usage
   *   The evaluated usage, or a host of one.
   *
   * @return array
   *   The usage with 'entity_type', 'entity_id', 'bundle', 'label',
   *   'revision_ids', 'last_used' and 'host'.
   */
  protected function describeUsage(array $usage): array {
    $entity = NULL;
    if ($this->entityTypeManager->hasDefinition($usage['entity_type'])) {
      $entity = $this->entityTypeManager->getStorage($usage['entity_type'])->load($usage['entity_id']);
    }

    return [
      'entity_type' => $usage['entity_type'],
      'entity_id' => $usage['entity_id'],
      'bundle' => $entity?->bundle(),
      'label' => $entity ? (string) $entity->label() : NULL,
      'revision_ids' => $usage['revision_ids'] ?? [],
      'last_used' => $usage['last_used'] ?? NULL,
      'host' => !empty($usage['host']) ? $this->describeUsage($usage['host']) : NULL,
    ];
  }

}
