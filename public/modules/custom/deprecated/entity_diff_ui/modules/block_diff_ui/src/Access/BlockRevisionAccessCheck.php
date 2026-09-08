<?php

namespace Drupal\block_diff_ui\Access;

use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity_diff_ui\Access\EntityRevisionAccessCheckBase;
use Symfony\Component\Routing\Route;

/**
 * Provides an access checker for Block Content revisions.
 *
 * @ingroup block_access
 */
class BlockRevisionAccessCheck extends EntityRevisionAccessCheckBase implements AccessInterface {

  const BLOCK_CONTENT_ENTITY = 'block_content';

  /**
   * Constructs a new BlockRevisionAccessCheck.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityStorage = $entity_type_manager->getStorage(self::BLOCK_CONTENT_ENTITY);
    $this->entityAccess = $entity_type_manager->getAccessControlHandler(self::BLOCK_CONTENT_ENTITY);
  }

  /**
   * Checks routing access for the Block Content revision.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param int $block_content_revision
   *   (optional) The block content revision ID.
   *   If not specified, but $block_content is, access
   *   is checked for that object's revision.
   * @param \Drupal\Core\Entity\EditorialContentEntityBase $block_content
   *   (optional) A Block Content object.
   *   Used for checking access to a block's default
   *   revision when $block_content_revision is unspecified.
   *   Ignored when $block_content_revision
   *   is specified. If neither $block_content_revision nor
   *   $block_content are specified, then
   *   access is denied.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account, ?int $block_content_revision = NULL, ?EditorialContentEntityBase $block_content = NULL) {
    $operation = $route->getRequirement('_access_block_revision');
    $map = [
      'view' => 'view all block content revisions',
      'update' => 'revert all block content revisions',
      'delete' => 'delete all block content revisions',
    ];

    return parent::getAccessResult($route, $account, $block_content_revision, $block_content, $map, $operation);
  }

}
