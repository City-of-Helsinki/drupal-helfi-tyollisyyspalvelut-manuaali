<?php

namespace Drupal\block_diff_ui\Controller;

use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\entity_diff_ui\Controller\EntityRevisionControllerBase;

/**
 * Returns responses for Block Content Revision routes.
 */
class BlockContentRevisionController extends EntityRevisionControllerBase {

  /**
   * The entity type ID of this controller should operate.
   *
   * @var string
   */
  const ENTITY_TYPE_ID = 'block_content';

  /**
   * Returns a form for revision overview page.
   *
   * @todo This might be changed to a view when the issue at this link is
   *   resolved: https://drupal.org/node/1863906
   *
   * @param \Drupal\Core\Entity\EditorialContentEntityBase $block_content
   *   The block content whose revisions are inspected.
   *
   * @return array
   *   Render array containing the revisions table for block contents.
   */
  public function revisionOverview(EditorialContentEntityBase $block_content) {
    return $this->formBuilder()->getForm('Drupal\entity_diff_ui\Form\RevisionOverviewForm', $block_content);
  }

  /**
   * Returns a table which shows the differences between two block revisions.
   *
   * @param \Drupal\Core\Entity\EditorialContentEntityBase $block_content
   *   The block content object whose revisions are compared.
   * @param int $left_revision
   *   Vid of the block revision from the left.
   * @param int $right_revision
   *   Vid of the block revision from the right.
   * @param string $filter
   *   If $filter == 'raw' raw text is compared (including html tags)
   *   If $filter == 'raw-plain'
   *   markdown function is applied to the text before comparison.
   *
   * @return array
   *   Table showing the diff between the two block revisions.
   */
  public function compareRevisions(EditorialContentEntityBase $block_content, $left_revision, $right_revision, $filter) {
    return $this->buildComparisonTable(self::ENTITY_TYPE_ID, $left_revision, $right_revision, $filter);
  }

  /**
   * Displays a block revision.
   *
   * @param \Drupal\Core\Entity\EditorialContentEntityBase $block_content
   *   The block content object to view the revision.
   * @param int $block_content_revision
   *   The block content revision ID.
   *
   * @return array
   *   An array suitable for \Drupal\Core\Render\RendererInterface::render().
   */
  public function revisionShow(EditorialContentEntityBase $block_content, int $block_content_revision) {
    return $this->buildRevisionView($block_content_revision, self::ENTITY_TYPE_ID);
  }

  /**
   * Page title callback for a revision.
   *
   * @param int $block_content_revision
   *   The revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($block_content_revision) {
    return $this->formatRevisionTitle($block_content_revision, self::ENTITY_TYPE_ID);
  }

}
