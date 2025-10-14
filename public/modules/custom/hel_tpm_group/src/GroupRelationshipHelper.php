<?php

namespace Drupal\hel_tpm_group;

use Drupal\ggroup\GroupHierarchyManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Provides service hel_tpm_group.group_relationship_helper.
 */
class GroupRelationshipHelper {

  use GroupSelectionTrait;

  /**
   * The group hierarchy manager.
   *
   * @var \Drupal\ggroup\GroupHierarchyManager
   */
  protected $groupHierarchyManager;

  /**
   * Constructs a InheritGroupPermissionCalculator object.
   *
   * @param \Drupal\ggroup\GroupHierarchyManager $hierarchy_manager
   *   The group hierarchy manager.
   */
  public function __construct(GroupHierarchyManagerInterface $hierarchy_manager) {
    $this->groupHierarchyManager = $hierarchy_manager;
  }

  /**
   * Get group IDs from node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node object.
   * @param bool $include_supergroups
   *   Boolean to determine if super groups should be returned.
   *
   * @return array
   *   Array of groups.
   */
  public function getGroupIdsByNode(NodeInterface $node, bool $include_supergroups = FALSE): array {
    return $this->getGroups($node, $include_supergroups, FALSE);
  }

}
