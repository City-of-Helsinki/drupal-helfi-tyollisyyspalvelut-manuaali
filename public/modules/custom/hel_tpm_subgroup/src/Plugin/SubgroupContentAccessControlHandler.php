<?php

namespace Drupal\hel_tpm_subgroup\Plugin;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Plugin\GroupContentAccessControlHandler;

/**
 * Provides access control for GroupContent entities and grouped entities.
 */
class SubgroupContentAccessControlHandler extends GroupContentAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account, $return_as_object = FALSE) {
    $result = parent::entityAccess($entity, $operation, $account, TRUE);

    // For entities that have our site-specific visibility field, open-up
    // view access to syllabi and resources when the instructor chooses
    // "Everyone at the School" or "Public / Anyone in the world" as the value.
    if ($operation == 'view' && $result->isForbidden()) {

    }

    return $return_as_object ? $result : $result->isAllowed();
  }

}
