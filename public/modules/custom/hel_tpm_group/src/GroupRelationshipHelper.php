<?php

namespace Drupal\hel_tpm_group;

use Drupal\node\NodeInterface;

/**
 * Provides service hel_tpm_group.group_relationship_helper.
 */
class GroupRelationshipHelper {

  use GroupSelectionTrait;

  /**
   * Get groups from node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node object.
   *
   * @return array
   *   Array of groups.
   */
  public function getGroupsByNode(NodeInterface $node): array {
    return $this->getGroups($node, FALSE, TRUE);
  }

}
