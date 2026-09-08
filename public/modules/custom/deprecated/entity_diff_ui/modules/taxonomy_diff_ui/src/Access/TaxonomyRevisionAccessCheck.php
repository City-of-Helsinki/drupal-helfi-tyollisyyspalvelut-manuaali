<?php

namespace Drupal\taxonomy_diff_ui\Access;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity_diff_ui\Access\EntityRevisionAccessCheckBase;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides an access checker for Taxonomy Term revisions.
 *
 * @ingroup taxonomy_access
 */
class TaxonomyRevisionAccessCheck extends EntityRevisionAccessCheckBase implements AccessInterface {

  /**
   * Constructs a new TaxonomyRevisionAccessCheck.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityStorage = $entity_type_manager->getStorage('taxonomy_term');
    $this->entityAccess = $entity_type_manager->getAccessControlHandler('taxonomy_term');
  }

  /**
   * Checks routing access for the Taxonomy Term revision.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param int $taxonomy_term_revision
   *   (optional) The term revision ID. If not specified, but $term is, access
   *   is checked for that object's revision.
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   (optional) A Taxonomy Term object. Used for
   *   checking access to a term's default
   *   revision when $taxonomy_term_revision is unspecified.
   *   Ignored when $taxonomy_term_revision
   *   is specified. If neither $taxonomy_term_revision nor
   *   $taxonomy_term are specified, then
   *   access is denied.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account, ?int $taxonomy_term_revision = NULL, ?TermInterface $taxonomy_term = NULL) {
    $operation = $route->getRequirement('_access_taxonomy_revision');
    $map = [
      'view' => 'view all taxonomy term revisions',
      'update' => 'revert all taxonomy term revisions',
      'delete' => 'delete all taxonomy term revisions',
    ];

    return parent::getAccessResult($route, $account, $taxonomy_term_revision, $taxonomy_term, $map, $operation);
  }

}
