<?php

namespace Drupal\entity_diff_ui\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides an access checker for entity revisions.
 *
 * @ingroup entity_access
 */
abstract class EntityRevisionAccessCheckBase {

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * The entity access control handler.
   *
   * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface
   */
  protected $entityAccess;

  /**
   * A static cache of access checks.
   *
   * @var array
   */
  protected $access = [];

  /**
   * Checks routing access for the entity revision.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param int $revision
   *   (optional) The entity revision ID.
   *   If not specified, but $entity is, access
   *   is checked for that object's revision.
   * @param \Drupal\Core\Entity\EditorialContentEntityBase $entity
   *   (optional) An Entity object. Used for checking access to the default
   *   revision when $revision is unspecified. Ignored when $revision
   *   is specified. If neither $revision nor $entity are specified, then
   *   access is denied.
   * @param array $map
   *   Permissions for all entity bundle types.
   * @param string $operation
   *   The operation that this route will conduct.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function getAccessResult(Route $route, AccountInterface $account, ?int $revision = NULL, ?EditorialContentEntityBase $entity = NULL, array $map, string $operation = 'view') {
    // When $revision is specified, instead of the entity.
    if ($revision) {
      $entity = $this->entityStorage->loadRevision($revision);
    }

    if ($entity) {
      $bundle = $entity->bundle();
      $type_map = [
        'view' => "view $bundle revisions",
        'update' => "revert $bundle revisions",
        'delete' => "delete $bundle revisions",
      ];
    }
    else {
      return AccessResult::forbidden('No access to the revision.');
    }

    return AccessResult::allowedIf($this->checkAccess($entity, $account, $map, $type_map, $operation))->cachePerPermissions()->addCacheableDependency($entity);
  }

  /**
   * Checks entity revision access.
   *
   * @param \Drupal\Core\Entity\EditorialContentEntityBase $entity
   *   The entity to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A user object representing the user for whom the operation is to be
   *   performed.
   * @param array $map
   *   Permissions for all entity bundle types.
   * @param array $type_map
   *   Permissions for specific entity bundle type.
   * @param string $op
   *   (optional) The specific operation being checked. Defaults to 'view.'.
   *
   * @return bool
   *   TRUE if the operation may be performed, FALSE otherwise.
   */
  public function checkAccess(EditorialContentEntityBase $entity, AccountInterface $account, array $map, array $type_map, $op = 'view') {
    // Whether current user has the access of the operation
    // to the entity.
    $has_entity_access = $this->entityAccess->access($this->entityStorage->load($entity->id()), $op, $account);

    if (!$entity || !$has_entity_access || !isset($map[$op]) || !isset($type_map[$op])) {
      // If there was no entity to check against, or the $op was not one of the
      // supported ones, or access to the entity, we return access denied.
      return FALSE;
    }

    // Statically cache access by revision ID, language code, user account ID,
    // and operation.
    $langcode = $entity->language()->getId();
    $cid = $entity->getRevisionId() . ':' . $langcode . ':' . $account->id() . ':' . $op;

    if (!isset($this->access[$cid])) {
      // Perform basic permission checks first.
      if (!$account->hasPermission($map[$op]) && !$account->hasPermission($type_map[$op])) {
        $this->access[$cid] = FALSE;
        return FALSE;
      }
      // If operation is 'view' and current user
      // has the view access to the entity,
      // display the revisions tab.
      if ($op === 'view') {
        $this->access[$cid] = TRUE;
      }
      else {
        // There should be at least two revisions.
        // If the vid of the given entity
        // and the vid of the default revision differ, then we already have two
        // different revisions so there is no need for a separate database
        // check. Also, if you try to revert to or delete the default revision,
        // that's not good.
        if ($entity->isDefaultRevision() && ($this->entityStorage->countDefaultLanguageRevisions($entity) == 1 || $op === 'update' || $op === 'delete')) {
          $this->access[$cid] = FALSE;
        }
        else {
          // First check the access to the default revision and finally, if the
          // entity passed in is not the default revision then check access to
          // that, too.
          $this->access[$cid] = $has_entity_access && ($entity->isDefaultRevision() || $this->entityAccess->access($entity, $op, $account));
        }
      }
    }

    return $this->access[$cid];
  }

}
