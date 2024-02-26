<?php

declare(strict_types = 1);

namespace Drupal\hel_tpm_group;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\node\NodeInterface;

/**
 * Missing updatees service.
 */
class ServiceMissingUpdatees {

  /**
   * Static variable for field mappings.
   *
   * @var string[]
   */
  private static array $updateeFields = [
    'field_responsible_municipality' => 'field_responsible_updatee',
    'field_service_producer' => 'field_service_provider_updatee',
  ];

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private EntityTypeManager $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Entity type manager service.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Get all services with missing updatees.
   *
   * @param int|null $group_id
   *   Group id.
   * @param bool $nids_only
   *   Boolean to decide whether to return
   *   array of nids or nids mapped to fields.
   *
   * @return array|mixed|null
   *   -
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getGroupServiceMissingUpdatee(int $group_id = NULL, bool $nids_only = FALSE) {
    if (empty($group_id)) {
      return NULL;
    }

    $result =& drupal_static(__CLASS__ . '-' . $group_id . '-' . $nids_only);
    if (!empty($result)) {
      return $result;
    }

    $result = [];

    $node_storage = $this->entityTypeManager->getStorage('node');

    // Get all nodes where selected group is either responsible municipality or
    // service provider.
    $query = $node_storage->getQuery();
    $query->condition('type', 'service');
    $query->condition('status', 1);
    $query->accessCheck(FALSE);
    $or = $query->orConditionGroup();
    foreach (self::$updateeFields as $group_ref => $user_ref) {
      $or->condition($group_ref, $group_id);
    }
    $query->condition($or);
    $nodes = $query->execute();

    if (empty($nodes)) {
      return [];
    }

    // Load revision provided by entityquery.
    $nodes = $node_storage->loadMultipleRevisions(array_keys($nodes));

    // Go through nodes and corresponding fields.
    foreach ($nodes as $node) {
      $err = $this->validateReferences($node, $group_id);
      // If error is set add current node to array.
      if (!empty($err)) {
        if ($nids_only === TRUE) {
          $result[] = $node->id();
          continue;
        }
        $result[$group_ref][$user_ref][] = [$node->id() => $err];
      }
    }

    return $result;
  }

  /**
   * Validate updatee fields from node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node object.
   * @param int $group_id
   *   Group id.
   *
   * @return array
   *   -
   */
  public function validateReferences(NodeInterface $node, int $group_id) {
    $err = NULL;
    foreach (self::$updateeFields as $group_ref => $user_ref) {
      // Only check fields that correspond only selected group.
      $field = $node->{$group_ref}->getValue();
      if (empty($field) || (int) $field[0]['target_id'] != $group_id) {
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
   * Validation for group id.
   *
   * @param int $gid
   *   Group id.
   *
   * @return bool
   *   Return true/false
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function validateGroupId(int $gid) {
    $query = $this->entityTypeManager->getStorage('group')->getQuery();
    $result = $query->condition('id', $gid)
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    return $result > 0;
  }

}
