<?php

namespace Drupal\hel_tpm_contact_info;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the contact info entity type.
 */
class ContactInfoAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view contact info');

      case 'update':
        return AccessResult::allowedIfHasPermissions($account, ['edit contact info', 'administer contact info'], 'OR');

      case 'delete':
        return AccessResult::allowedIfHasPermissions($account, ['delete contact info', 'administer contact info'], 'OR');

      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, ['create contact info', 'administer contact info'], 'OR');
  }

}
