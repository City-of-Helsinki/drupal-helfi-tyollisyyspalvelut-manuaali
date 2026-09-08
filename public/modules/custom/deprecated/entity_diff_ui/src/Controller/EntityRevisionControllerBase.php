<?php

namespace Drupal\entity_diff_ui\Controller;

use Drupal\diff\Controller\PluginRevisionController;

/**
 * Returns responses for Taxonomy Term Revision routes.
 */
abstract class EntityRevisionControllerBase extends PluginRevisionController {

  /**
   * The term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * Returns a table which shows the differences between two entity revisions.
   *
   * @param string $entity_type_id
   *   The id of entity type of the storage.
   * @param int $left_revision
   *   Vid of the entity revision from the left.
   * @param int $right_revision
   *   Vid of the entity revision from the right.
   * @param string $filter
   *   If $filter == 'raw' raw text is compared (including html tags)
   *   If $filter == 'raw-plain' markdown function is
   *   applied to the text before comparison.
   *
   * @return array
   *   Table showing the diff between the two term revisions.
   */
  public function buildComparisonTable($entity_type_id, $left_revision, $right_revision, $filter) {
    $storage = $this->getEntityStorage($entity_type_id);
    $route_match = \Drupal::routeMatch();
    $left_revision = $storage->loadRevision($left_revision);
    $right_revision = $storage->loadRevision($right_revision);
    $build = $this->compareEntityRevisions($route_match, $left_revision, $right_revision, $filter);
    return $build;
  }

  /**
   * Build a entity revision view.
   *
   * @param int $revision
   *   The node revision ID.
   * @param string $entity_type_id
   *   The entity type of this revision.
   *
   * @return array
   *   An array suitable for \Drupal\Core\Render\RendererInterface::render().
   */
  public function buildRevisionView($revision, $entity_type_id) {
    $term = $this->getEntityStorage($entity_type_id)->loadRevision($revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder($entity_type_id);
    $view_mode = 'full';

    return $view_builder->view($term, $view_mode);
  }

  /**
   * Get taxonomy term storage object.
   *
   * @param string $entity_type_id
   *   The id of entity type of the storage.
   *
   * @return \Drupal\taxonomy\TermStorageInterface
   *   Foo.
   */
  protected function getEntityStorage($entity_type_id) {
    if (!isset($this->termStorage)) {
      $this->termStorage = $this->entityTypeManager()->getStorage($entity_type_id);
    }

    return $this->termStorage;
  }

  /**
   * Format an entity revision title.
   *
   * @param int $revision_id
   *   The node revision ID.
   * @param string $entity_type_id
   *   The entity type of this revision.
   *
   * @return array
   *   An array suitable for \Drupal\Core\Render\RendererInterface::render().
   */
  protected function formatRevisionTitle($revision_id, $entity_type_id) {
    $revision = $this->getEntityStorage($entity_type_id)->loadRevision($revision_id);
    return $this->t('Revision of %title from %date',
      [
        '%title' => $revision->label(),
        '%date' => \Drupal::service('date.formatter')->format($revision->getRevisionCreationTime()),
      ]
    );
  }

}
