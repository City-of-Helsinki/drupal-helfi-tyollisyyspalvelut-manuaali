<?php

namespace Drupal\hel_tpm_general;

use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeInterface;

/**
 * Provides access control for the revision list of nodes.
 */
final class RevisionListAccessController {

  /**
   * Checks if the user has update access to the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check access for.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result indicating whether the user is allowed or denied.
   */
  public function access(NodeInterface $node) {
    $access = $node->access('update');
    return AccessResult::allowedIf($access);
  }

}
