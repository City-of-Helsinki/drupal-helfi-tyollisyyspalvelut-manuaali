<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_stats;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * @todo Add class description.
 */
final class RevisionHistoryService {

  /**
   * Constructs a RevisionHistory object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $connection,
  ) {}

  /**
   * Create published date node.
   *
   * @param $published_revision
   *   Published revision.
   * @param $previous_revision
   *   Previsous revision.
   *
   * @return void
   *  Void.
   */
  function createServicePublishedRow($published_revision, $previous_revision) {
    $publish_storage = $this->entityTypeManager->getStorage('service_published_row');
    $node_storage = $this->entityTypeManager->getStorage('node');
    $node_revision = $node_storage->loadRevision($published_revision->content_entity_revision_id);
    $previous_revision_node = $node_storage->loadRevision($previous_revision->content_entity_revision_id);

    if (empty($node_revision)) {
      return;
    }

    $publish_storage->create([
      'uid' => \Drupal::currentUser()->id(),
      'nid' => $published_revision->content_entity_id,
      'langcode' => $published_revision->langcode,
      'publish_vid' => $published_revision->content_entity_revision_id,
      'publish_date' => $node_revision->getRevisionCreationTime(),
      'previous_vid' => $previous_revision->content_entity_revision_id,
      'previous_date' => $previous_revision_node->getRevisionCreationTime(),
      'previous_state' => $previous_revision->moderation_state
    ])->save();
  }

  /**
   * Get database row for given revision id.
   *
   * @param $revision_id
   *
   * @return array
   *   Content moderation state revision row.
   *
   * @throws \Exception
   */
  public function getPublishedRevisionRow($revision_id, $langcode) {
    $publish_revisions = $this->connection->select('content_moderation_state_field_revision', 'cm')
      ->fields('cm')
      ->condition('cm.content_entity_revision_id', $revision_id)
      ->condition('cm.langcode', $langcode)
      ->condition('cm.moderation_state', 'published')
      ->execute();
    return $publish_revisions->fetchAll();
  }

  /**
   * Get published revisions.
   *
   * @return array
   *   Array of published revisions.
   *
   * @throws \Exception
   */
  public function getPublishedRevisions() : array {
    $publish_revisions = $this->connection->select('content_moderation_state_field_revision', 'cm')
      ->fields('cm')
      ->condition('cm.moderation_state', 'published')
      ->execute();
    return $publish_revisions->fetchAll();
  }

  /**
   * Get previous revisions for current published row.
   *
   * @param $row
   *  Published revision database row.
   *
   * @return object|null
   *  Last revision with previous status.
   *
   * @throws \Exception
   */
  public function getPreviousRevision($row) :? object {
    $query = $this->connection->select('content_moderation_state_field_revision', 'cm')
        ->fields('cm')
        ->condition('cm.content_entity_id', $row->content_entity_id)
        ->condition('cm.content_entity_revision_id', $row->content_entity_revision_id, "<")
        ->condition('cm.langcode', $row->langcode)
        ->orderBy('cm.revision_id', 'DESC');

    $previous_query = clone $query;
    $previous_status = $previous_query->range(0, 1)
      ->execute()->fetchAll();

    if (empty($previous_status) || $previous_status[0]->moderation_state != 'ready_to_publish') {
      return NULL;
    }

    $older_revisions = $query->execute()->fetchAll();

    // Go through older revisions until revision status changes.
    foreach ($older_revisions as $revision) {
      if ($revision->moderation_state != $previous_status[0]->moderation_state) {
        break;
      }
      $prev_rev = $revision;
    }

    return $prev_rev;
  }
}
