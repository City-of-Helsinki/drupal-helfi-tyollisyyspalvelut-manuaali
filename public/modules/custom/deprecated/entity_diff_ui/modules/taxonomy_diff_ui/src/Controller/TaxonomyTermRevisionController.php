<?php

namespace Drupal\taxonomy_diff_ui\Controller;

use Drupal\entity_diff_ui\Controller\EntityRevisionControllerBase;
use Drupal\taxonomy\TermInterface;

/**
 * Returns responses for Taxonomy Term Revision routes.
 */
class TaxonomyTermRevisionController extends EntityRevisionControllerBase {

  /**
   * The entity type ID of this controller should operate.
   *
   * @var string
   */
  const ENTITY_TYPE_ID = 'taxonomy_term';

  /**
   * Returns a form for revision overview page.
   *
   * @todo This might be changed to a view when the issue at this link is
   *   resolved: https://drupal.org/node/1863906
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The Taxonomy Term whose revisions are inspected.
   *
   * @return array
   *   Render array containing the revisions table for $taxonomy_term.
   */
  public function revisionOverview(TermInterface $taxonomy_term) {
    return $this->formBuilder()->getForm('Drupal\entity_diff_ui\Form\RevisionOverviewForm', $taxonomy_term);
  }

  /**
   * Returns a table which shows the differences between two term revisions.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term object whose revisions are compared.
   * @param int $left_revision
   *   Vid of the term revision from the left.
   * @param int $right_revision
   *   Vid of the term revision from the right.
   * @param string $filter
   *   If $filter == 'raw' raw text is compared (including html tags)
   *   If $filter == 'raw-plain' markdown function is
   *   applied to the text before comparison.
   *
   * @return array
   *   Table showing the diff between the two term revisions.
   */
  public function compareTermRevisions(TermInterface $taxonomy_term, $left_revision, $right_revision, $filter) {
    return $this->buildComparisonTable(self::ENTITY_TYPE_ID, $left_revision, $right_revision, $filter);
  }

  /**
   * Displays a taxonomy revision.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy object to view the revision.
   * @param int $taxonomy_term_revision
   *   The taxonomy term revision ID.
   *
   * @return array
   *   An array suitable for \Drupal\Core\Render\RendererInterface::render().
   */
  public function revisionShow(TermInterface $taxonomy_term, int $taxonomy_term_revision) {
    return $this->buildRevisionView($taxonomy_term_revision, self::ENTITY_TYPE_ID);
  }

  /**
   * Page title callback for a revision.
   *
   * @param int $taxonomy_term_revision
   *   The revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($taxonomy_term_revision) {
    return $this->formatRevisionTitle($taxonomy_term_revision, self::ENTITY_TYPE_ID);
  }

}
