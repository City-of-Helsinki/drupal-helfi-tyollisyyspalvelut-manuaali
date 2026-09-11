<?php

namespace Drupal\media_diff_ui\Controller;

use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\entity_diff_ui\Controller\EntityRevisionControllerBase;

/**
 * Returns responses for media Revision routes.
 */
class MediaRevisionController extends EntityRevisionControllerBase {

  /**
   * The entity type ID of this controller should operate.
   *
   * @var string
   */
  const ENTITY_TYPE_ID = 'media';

  /**
   * Returns a form for revision overview page.
   *
   * @todo This might be changed to a view when the issue at this link is
   *   resolved: https://drupal.org/node/1863906
   *
   * @param \Drupal\Core\Entity\EditorialContentEntityBase $media
   *   The media whose revisions are inspected.
   *
   * @return array
   *   Render array containing the revisions table for medias.
   */
  public function revisionOverview(EditorialContentEntityBase $media) {
    return $this->formBuilder()->getForm('Drupal\entity_diff_ui\Form\RevisionOverviewForm', $media);
  }

  /**
   * Returns a table which shows the differences between two media revisions.
   *
   * @param \Drupal\Core\Entity\EditorialContentEntityBase $media
   *   The media object whose revisions are compared.
   * @param int $left_revision
   *   Vid of the media revision from the left.
   * @param int $right_revision
   *   Vid of the media revision from the right.
   * @param string $filter
   *   If $filter == 'raw' raw text is compared (including html tags)
   *   If $filter == 'raw-plain' markdown function is applied
   *   to the text before comparison.
   *
   * @return array
   *   Table showing the diff between the two media revisions.
   */
  public function compareRevisions(EditorialContentEntityBase $media, $left_revision, $right_revision, $filter) {
    return $this->buildComparisonTable(self::ENTITY_TYPE_ID, $left_revision, $right_revision, $filter);
  }

  /**
   * Displays a media revision.
   *
   * @param \Drupal\Core\Entity\EditorialContentEntityBase $media
   *   The media object to view the revision.
   * @param int $media_revision
   *   The media revision ID.
   *
   * @return array
   *   An array suitable for \Drupal\Core\Render\RendererInterface::render().
   */
  public function revisionShow(EditorialContentEntityBase $media, int $media_revision) {
    return $this->buildRevisionView($media_revision, self::ENTITY_TYPE_ID);
  }

  /**
   * Page title callback for a revision.
   *
   * @param int $media_revision
   *   The revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($media_revision) {
    return $this->formatRevisionTitle($media_revision, self::ENTITY_TYPE_ID);
  }

}
