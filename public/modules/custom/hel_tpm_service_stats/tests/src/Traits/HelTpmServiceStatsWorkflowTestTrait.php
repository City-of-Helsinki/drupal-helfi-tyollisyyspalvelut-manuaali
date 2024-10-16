<?php

namespace Drupal\Tests\hel_tpm_service_stats\Traits;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Service manual workflow test trait.
 */
trait HelTpmServiceStatsWorkflowTestTrait {

  use UserCreationTrait;

  /**
   * Creates a group.
   *
   * @param array $values
   *   (optional) The values used to create the entity.
   *
   * @return \Drupal\group\Entity\GroupInterface
   *   The created group entity.
   */
  protected function createGroup(array $values = []) {
    $storage = $this->entityTypeManager->getStorage('group');
    $group = $storage->create($values + [
      'label' => $this->randomString(),
    ]);
    $group->enforceIsNew();
    $storage->save($group);
    return $this->reloadEntity($group);
  }

  /**
   * Create node with randomized title.
   *
   * @param array $values
   *   Array of values mapped for node.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Node entity interface.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createNode($values) {
    // Populate defaults array.
    $values += [
      'title' => $this->randomMachineName(8),
    ];
    // Create node object.
    $node = Node::create($values);
    $node->save();
    return $this->reloadEntity($node);
  }

  /**
   * Create users with given drupal roles.
   *
   * @param array $roles
   *   Array of roles.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\user\Entity\User
   *   User entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createUserWithRoles($roles) {
    $user = $this->createUser();
    if (empty($roles)) {
      return $user;
    }
    foreach ($roles as $role) {
      $user->addRole($role);
    }
    $user->save();
    return $this->reloadEntity($user);
  }

  /**
   * Set node content moderation state.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node object.
   * @param string $state
   *   Moderation state as a string.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   -
   */
  protected function setNodeModerationState(NodeInterface $node, string $state) {
    $node->set('moderation_state', $state);
    $node->save();
    return $this->reloadEntity($node);
  }

}
