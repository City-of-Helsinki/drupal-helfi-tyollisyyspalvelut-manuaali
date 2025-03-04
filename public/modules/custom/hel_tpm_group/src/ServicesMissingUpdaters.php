<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\node\NodeInterface;

/**
 * Check whether group services are missing updaters.
 */
class ServicesMissingUpdaters {

  /**
   * Static variable for field mappings.
   *
   * @var string[]
   */
  private static array $updatersFields = [
    'municipality' => 'field_responsible_updatee',
    'group' => 'field_service_provider_updatee',
  ];

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private EntityTypeManager $entityTypeManager;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $database;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   */
  public function __construct(EntityTypeManager $entityTypeManager, Connection $database) {
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
  }

  /**
   * Get group's services with missing updaters.
   *
   * @param int $group_id
   *   Group id.
   * @param bool $nids_only
   *   TRUE if array of nids should be returned, FALSE if nids mapped to fields
   *   should be returned.
   * @param bool $published_only
   *   TRUE if only checking published services, FALSE otherwise.
   *
   * @return array|null
   *   -
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getByGroup(int $group_id, bool $nids_only = FALSE, bool $published_only = FALSE): ?array {
    if (empty($group_id)) {
      return NULL;
    }

    $result = [];
    $skip_municipality = TRUE;
    $node_storage = $this->entityTypeManager->getStorage('node');
    $group = $this->entityTypeManager->getStorage('group')->load($group_id);

    $nodes = [
      'group' => $this->getServicesByGroup($group_id),
    ];
    // Only fetch nodes from municipality field only for organisations.
    if ($group->bundle() == 'organisation') {
      $skip_municipality = FALSE;
      $nodes += ['municipality' => $this->getMunicipalityNodes($group_id)];
    }

    // Load revision provided by entityquery.
    $nodes['group'] = $node_storage->loadMultiple(array_keys($nodes['group']));

    // Go through nodes and corresponding fields.
    foreach ($nodes as $source) {
      foreach ($source as $node) {
        // If required, only take into account services that are published.
        if ($published_only && $node->get('moderation_state')->value !== 'published') {
          continue;
        }

        $err = $this->validateReferences($node, $skip_municipality);
        // If error is set add current node to array.
        if (!empty($err)) {
          if ($nids_only) {
            $result[] = $node->id();
          }
          else {
            $result[] = [
              'id' => $node->id(),
              'errors' => $err,
            ];
          }
        }
      }
    }

    return $result;
  }

  /**
   * Validate updatee fields from node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node object.
   * @param bool $skip_municipality
   *   Boolean whether to skip municipality field values.
   *
   * @return array|null
   *   -
   */
  public function validateReferences(NodeInterface $node, bool $skip_municipality = FALSE): ?array {
    $err = NULL;
    foreach (self::$updatersFields as $group_ref => $user_ref) {
      if ($skip_municipality && $group_ref == 'municipality') {
        continue;
      }
      // Load user object.
      $user = $node->{$user_ref}->referencedEntities();
      // If loaded user object is empty user is removed from systems.
      // Add node to result array.
      if (empty($user)) {
        $err[$user_ref] = 'user missing from system';
      }
      else {
        $user = reset($user);
        // If user doesn't have update access add to result array.
        if (!$node->access('update', $user) || $user->isBlocked()) {
          $err[$user_ref] = 'user has no update access';
        }
      }
    }
    return $err;
  }

  /**
   * Get group services.
   *
   * @param int $group_id
   *   Group id.
   *
   * @return array
   *   Array of group ids
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getServicesByGroup(int $group_id): array {
    $result = $this->database->select('group_relationship_field_data', 'gr')
      ->fields('gr', ['entity_id'])
      ->condition('gid', $group_id)
      ->condition('plugin_id', 'group_node:service')
      ->execute()->fetchAllAssoc('entity_id');
    if (empty($result)) {
      return [];
    }
    return $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple(array_keys($result));
  }

  /**
   * Get services which refer given group in field_responsible_municipality.
   *
   * @param int $group_id
   *   Group id.
   *
   * @return array|int
   *   -
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getMunicipalityNodes(int $group_id): array|int {
    $node_storage = $this->entityTypeManager->getStorage('node');
    // Get all nodes where selected group is either responsible municipality or
    // service provider.
    $query = $node_storage->getQuery();
    $query->condition('type', 'service');
    $query->accessCheck(FALSE);
    $query->condition('field_responsible_municipality', $group_id);
    $nodes = $query->execute();

    if (empty($nodes)) {
      return [];
    }

    return $this->entityTypeManager
      ->getStorage('node')
      ->loadMultipleRevisions(array_keys($nodes));
  }

}
