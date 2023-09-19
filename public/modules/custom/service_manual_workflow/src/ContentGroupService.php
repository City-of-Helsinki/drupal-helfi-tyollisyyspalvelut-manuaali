<?php

namespace Drupal\service_manual_workflow;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupInterface;

/**
 * ContentGroupService service.
 */
class ContentGroupService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ContentGroupService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   * @param \Drupal\group\Entity\GroupInterface $group
   *
   * @return null
   */
  public function getAccountGroupRoles(AccountProxyInterface $account, GroupInterface $group) {
    $member = $group->getMember($account);
    if (empty($member)) {
      return NULL;
    }
    if (empty($member->getGroupContent()->group_roles)) {
      return NULL;
    }
    return $member->getGroupContent()->group_roles->entity;
  }

  /**
   * Method description.
   */
  public function getGroupsWithEntity(EntityInterface $entity) {
    $groups = [];

    $group_contents = GroupContent::loadByEntity($entity);
    foreach ($group_contents as $group_content) {
      $groups[] = $group_content->getGroup();
    }

    return $groups;
  }

}
