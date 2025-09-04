<?php

namespace Drupal\hel_tpm_update_reminder;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Service for managing and updating reminders for service providers.
 */
class UpdateReminderUserService {

  /**
   * The machine name for the service provider field.
   *
   * @var string
   */
  protected $serviceProducerField = 'field_service_producer';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(Connection $database, EntityTypeManagerInterface $entityTypeManager) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Retrieves service provider updaters for published services with reminders.
   *
   * @return array
   *   An array of processed service provider updaters.
   */
  public function getServicesToRemind(): array {

    // Get published service nodes.
    $serviceIds = $this->fetchPublishedServiceIds();
    if (empty($serviceIds)) {
      return [];
    }

    $services_with_updaters = $this->fetchServiceProviderUpdaters($serviceIds);

    // Process services to find due reminders.
    return $this->processUpdaters($services_with_updaters);
  }

  /**
   * Fetches service provider updaters for the provided service revision IDs.
   *
   * @param array $serviceIds
   *   An associative array of service revision IDs where keys are revision IDs
   *   and values can be any associated data or metadata.
   *
   * @return array
   *   An array of producer rows. Each row contains producer information along
   *   with a list of updaters associated with the producer's group. If a group
   *   has no updaters or does not exist, an empty list will be returned for
   *   that producer's updaters key.
   */
  public function fetchServiceProviderUpdaters($serviceIds) {
    if (empty($serviceIds)) {
      return [];
    }

    $producerTargetField = 'gid';

    // Fetch producers for the given service revision IDs.
    $producerRows = $this->database
      ->select('group_relationship_field_data', 'f')
      ->condition('f.entity_id', $serviceIds, 'IN')
      ->condition('f.plugin_id', 'group_node:service')
      ->fields('f', ['entity_id', $producerTargetField])
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($producerRows)) {
      return [];
    }

    // Collect unique group IDs referenced by producers.
    $groupIds = [];
    foreach ($producerRows as $row) {
      if (isset($row[$producerTargetField])) {
        $groupIds[$row[$producerTargetField]] = TRUE;
      }
    }
    $groupIds = array_keys($groupIds);

    if (empty($groupIds)) {
      // No groups found; return producers with empty updaters lists.
      foreach ($producerRows as &$row) {
        $row['updaters'] = [];
      }
      return $producerRows;
    }

    // Fetch members for the relevant groups.
    $groupMemberRows = $this->database
      ->select('group_relationship_field_data', 'gr')
      ->fields('gr', ['gid', 'entity_id'])
      ->condition('gr.plugin_id', 'group_membership')
      ->condition('gr.gid', $groupIds, 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    // Index members by group ID for fast lookup.
    $groupMembersByGroupId = [];
    foreach ($groupMemberRows as $member) {
      $gid = $member['gid'] ?? NULL;
      $uid = $member['entity_id'] ?? NULL;
      if ($gid !== NULL && $uid !== NULL) {
        $groupMembersByGroupId[$gid][] = $uid;
      }
    }

    // Attach updaters to each producer row without mutating by reference.
    $result = [];
    foreach ($producerRows as $row) {
      $gid = $row[$producerTargetField] ?? NULL;
      $row['updaters'] = ($gid !== NULL) ? ($groupMembersByGroupId[$gid] ?? []) : [];
      $result[] = $row;
    }

    return $result;

  }

  /**
   * Fetches all published service node IDs.
   *
   * @return array
   *   An array of service node IDs.
   */
  public function fetchPublishedServiceIds(): array {
    return $this->entityTypeManager->getStorage('node')->getQuery()
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
   * Processes services to identify updaters and determines reminder services.
   *
   * @param array $services
   *   An array of services, each containing details such as entity ID,
   *   updaters, and revision data.
   *
   * @return array
   *   An array of services that require update reminders. Each element contains
   *   service entity IDs and their associated revision details.
   */
  protected function processUpdaters(array $services): array {
    $reminderServices = [];
    foreach ($services as $service) {
      if (empty($service['updaters'])) {
        continue;
      }
      $latestRevision = $this->fetchLatestRevision(
        $service['entity_id'],
        $service['updaters']
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
   * Fetches the latest revision for the specified node ID by given updaters.
   *
   * @param int $nodeId
   *   The ID of the node to fetch the latest revision for.
   * @param array $updaters
   *   An array of user IDs to filter revisions by.
   *
   * @return array|null
   *   An associative array containing the latest revision details,
   *   or NULL if no matching revisions are found.
   */
  protected function fetchLatestRevision(int $nodeId, array $updaters): ?array {
    if (empty($updaters)) {
      return NULL;
    }
    $result = $this->database->select('node_revision', 'nr')
      ->fields('nr', ['nid', 'vid', 'revision_uid', 'revision_timestamp'])
      ->condition('nr.nid', $nodeId)
      ->condition('nr.revision_uid', $updaters, 'IN')
      ->orderBy('nr.vid', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return !empty($result) ? $result[0] : NULL;
  }

}
