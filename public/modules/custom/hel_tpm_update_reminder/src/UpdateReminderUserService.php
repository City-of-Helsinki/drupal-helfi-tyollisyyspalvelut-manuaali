<?php

namespace Drupal\hel_tpm_update_reminder;

use Drupal\Core\Database\Connection;
use Drupal\node\NodeInterface;

/**
 * Service for managing and updating reminders for service providers.
 */
class UpdateReminderUserService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Retrieves service provider updaters for published services with reminders.
   *
   * @return array
   *   An array of processed service provider updaters.
   */
  public function getServicesToRemind(): array {
    $fieldUpdater = 'field_service_provider_updatee';

    // Get published service nodes.
    $serviceIds = $this->fetchPublishedServiceIds();
    if (empty($serviceIds)) {
      return [];
    }

    // Fetch services containing updaters.
    $servicesWithUpdaters = $this->fetchUpdatersForRevisions($fieldUpdater, $serviceIds);
    if (empty($servicesWithUpdaters)) {
      return [];
    }

    // Process services to find due reminders.
    return $this->processUpdaters($servicesWithUpdaters, $fieldUpdater);
  }

  /**
   * Fetches all published service node IDs.
   *
   * @return array
   *   An array of service node IDs.
   */
  public function fetchPublishedServiceIds(): array {
    return \Drupal::entityQuery('node')
      ->condition('type', 'service')
      ->condition('status', NodeInterface::PUBLISHED)
      ->accessCheck(FALSE)
      ->execute();
  }

  /**
   * Fetches services with updaters from the database.
   *
   * @param string $field
   *   The field containing updater references.
   * @param array $serviceIds
   *   Published service node IDs.
   *
   * @return array
   *   Services with updaters.
   */
  protected function fetchUpdatersForRevisions(string $field, array $serviceIds): array {
    return $this->database->select('node_revision__' . $field, 'f')
      ->condition('f.revision_id', array_keys($serviceIds), 'IN')
      ->fields('f', ['entity_id', 'revision_id', $field . '_target_id'])
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Processes services with updaters and finds those due for reminders.
   *
   * @param array $services
   *   The services with updaters.
   * @param string $field
   *   The updater field name.
   *
   * @return array
   *   Services due for reminders.
   */
  protected function processUpdaters(array $services, string $field): array {
    $reminderServices = [];
    foreach ($services as $service) {
      $latestRevision = $this->fetchLatestRevisionForUpdater(
        $service['entity_id'],
        $service[$field . '_target_id']
      );

      if (empty($latestRevision)) {
        $reminderServices[$service['entity_id']] = [
          'nid' => $service['entity_id'],
          'revision_id' => $service['revision_id'],
        ];
        continue;
      }
      if ($latestRevision['revision_timestamp'] < UpdateReminderUtility::getFirstLimitTimestamp()) {
        $reminderServices[$service['entity_id']] = $latestRevision;
      }
    }
    return $reminderServices;
  }

  /**
   * Fetches the latest revision for a specific updater.
   *
   * @param int $nodeId
   *   The parent service node ID.
   * @param int $updaterUserId
   *   The updater user ID.
   *
   * @return array|null
   *   The latest revision data or NULL if none found.
   */
  protected function fetchLatestRevisionForUpdater(int $nodeId, int $updaterUserId): ?array {
    $result = $this->database->select('node_revision', 'nr')
      ->fields('nr', ['nid', 'vid', 'revision_uid', 'revision_timestamp'])
      ->condition('nr.nid', $nodeId)
      ->condition('nr.revision_uid', $updaterUserId)
      ->orderBy('nr.revision_timestamp', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return !empty($result) ? $result[0] : NULL;
  }

}
