<?php

namespace Drupal\hel_tpm_group\Plugin\Group\RelationHandler;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Access\GroupAccessResult;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Plugin\Group\RelationHandlerDefault\AccessControl as BaseAccessControl;

class LeaveGroupAccessControl extends BaseAccessControl  {

  /**
   * Prevent user from leaving group without 'leave group' permssion.
   *
   * @param \Drupal\group\Entity\GroupRelationshipInterface $group_relationship
   * @param $operation
   * @param \Drupal\Core\Session\AccountInterface $account
   * @param $return_as_object
   *
   * @return bool|\Drupal\Core\Access\AccessResult|\Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden|\Drupal\Core\Access\AccessResultInterface|\Drupal\Core\Access\AccessResultNeutral
   */
  public function relationshipAccess(GroupRelationshipInterface $group_relationship, $operation, AccountInterface $account, $return_as_object = FALSE) {
    $result = parent::relationshipAccess($group_relationship, $operation, $account, $return_as_object);

    // Don't process anything else than delete operation.
    if ($operation !== 'delete' || !$result instanceof AccessResultAllowed) {
      return $result;
    }

    $is_owner = $group_relationship->getOwnerId() === $account->id();

    if (!$is_owner) {
      return $result;
    }

    $permissions = [
      $this->permissionProvider->getPermission($operation, 'relationship', 'own')
    ];

    $permissions = array_filter($permissions);

    // If we still have permissions left, check for access.
    $result = AccessResult::neutral();
    if (!empty($permissions)) {
      $result = GroupAccessResult::allowedIfHasGroupPermissions($group_relationship->getGroup(), $account, $permissions);
    }

    // Force forbidden if user doesn't have leave group permission
    // to prevent any other module or permission to override the result.
    return AccessResultForbidden::forbiddenIf($result->isNeutral());
  }

}