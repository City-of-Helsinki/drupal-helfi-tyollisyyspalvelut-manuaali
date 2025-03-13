<?php

namespace Drupal\hel_tpm_group\Plugin\Group\RelationHandler;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Access\GroupAccessResult;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Plugin\Group\RelationHandlerDefault\AccessControl as BaseAccessControl;
use Drupal\user\Entity\User;

/**
 * Access controller to prevent user from leaving group without permission.
 */
class LeaveGroupAccessControl extends BaseAccessControl {

  /**
   * {@inheritdoc}
   */
  public function relationshipAccess(
    GroupRelationshipInterface $group_relationship,
    $operation,
    AccountInterface $account,
    $return_as_object = FALSE,
  ) {
    $result = parent::relationshipAccess($group_relationship, $operation, $account, $return_as_object);

    // Don't process anything else than group membership delete operations that
    // targets the current account and are allowed by default.
    if ($operation !== 'delete'
      || $group_relationship->getRelationshipType()->getPluginId() !== 'group_membership'
      || !$result instanceof AccessResultAllowed
      || !$group_relationship->getEntity() instanceof User
      || $group_relationship->getEntity()->id() !== $account->id()
    ) {
      return $result;
    }

    // Force forbidden if user doesn't have 'leave group' permission to prevent
    // any other module or permission to override the result.
    $result = AccessResult::neutral();
    if (!empty($permission = $this->permissionProvider->getPermission($operation, 'relationship', 'own'))) {
      $result = GroupAccessResult::allowedIfHasGroupPermissions($group_relationship->getGroup(), $account, [$permission]);
    }
    return AccessResultForbidden::forbiddenIf(!$result->isAllowed());
  }

}
