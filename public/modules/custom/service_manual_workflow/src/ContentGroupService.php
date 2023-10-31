<?php

namespace Drupal\service_manual_workflow;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupRelationship;

/**
 * Content group service.
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
   * Get groups from content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity object.
   *
   * @return array
   *   Array of groups.
   */
  public function getGroupsWithEntity(ContentEntityInterface $entity) {
    $groups = [];

    $group_contents = GroupRelationship::loadByEntity($entity);
    foreach ($group_contents as $group_content) {
      $groups[] = $group_content->getGroup();
    }

    return $groups;
  }

}
