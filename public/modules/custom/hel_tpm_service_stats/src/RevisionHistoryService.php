<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_stats;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\content_moderation\ModerationInformationInterface;

/**
 * Revision history service.
 */
final class RevisionHistoryService {

  /**
   * Constructs a RevisionHistory object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $connection,
    private readonly ModerationInformationInterface $moderationInformation,
    private readonly AccountInterface $currentUser,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Create published date node.
   *
   * @param \stdClass $published_revision
   *   Published revision.
   * @param \stdClass $previous_revision
   *   Previsous revision.
   *
   * @return void
   *   Void.
   */
  public function createServicePublishedRow($published_revision, $previous_revision) {
    $publish_storage = $this->entityTypeManager->getStorage('service_published_row');
    $node_storage = $this->entityTypeManager->getStorage('node');
    $node_revision = $node_storage->loadRevision($published_revision->content_entity_revision_id);
    $previous_revision_node = $node_storage->loadRevision($previous_revision->content_entity_revision_id);

    if (empty($node_revision)) {
      return;
    }

    $publish_storage->create([
      'uid' => $this->currentUser->id(),
      'nid' => $published_revision->content_entity_id,
      'langcode' => $published_revision->langcode,
      'publish_vid' => $published_revision->content_entity_revision_id,
      'publish_date' => $node_revision->getRevisionCreationTime(),
      'previous_vid' => $previous_revision->content_entity_revision_id,
      'previous_date' => $previous_revision_node->getRevisionCreationTime(),
      'previous_state' => $previous_revision->moderation_state,
    ])->save();
  }

  /**
   * Get database row for given revision id.
   *
   * @param int $revision_id
   *   Revision id of published revision.
   * @param string $langcode
   *   Revision langcode.
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
      ->condition('cm.revision_translation_affected', 1)
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
      ->condition('cm.revision_translation_affected', 1)
      ->execute();
    return $publish_revisions->fetchAll();
  }

  /**
   * Get previous revisions for current published row.
   *
   * @param \stdClass $row
   *   Published revision database row.
   *
   * @return object|null
   *   Last revision with previous status.
   *
   * @throws \Exception
   */
  public function getPreviousRevision($row) :? object {
    $query = $this->connection->select('content_moderation_state_field_revision', 'cm')
      ->fields('cm')
      ->condition('cm.content_entity_id', $row->content_entity_id)
      ->condition('cm.content_entity_revision_id', $row->content_entity_revision_id, "<")
      ->condition('cm.langcode', $row->langcode)
      ->condition('cm.revision_translation_affected', 1)
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

  /**
   * Get time since last service state change.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Selected entity.
   *
   * @return int
   *   Last state change in days.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTimeSinceLastStateChange(EntityInterface $entity): int {
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());

    if (!$this->moderationInformation->isModeratedEntity($entity)) {
      return 0;
    }

    $current_state = $entity->moderation_state->getValue();

    if (empty($current_state)) {
      return 0;
    }

    $state = $current_state[0]['value'];

    $revisions = $this->connection->select('content_moderation_state_field_revision', 'cm')
      ->fields('cm')
      ->condition('cm.content_entity_id', $entity->id())
      ->condition('cm.content_entity_revision_id', $entity->getRevisionId(), "<=")
      ->condition('cm.langcode', $entity->language()->getId())
      ->condition('cm.revision_translation_affected', 1)
      ->orderBy('cm.revision_id', 'DESC')
      ->execute()->fetchAll();

    foreach ($revisions as $revision) {
      if ($revision->moderation_state === $state) {
        $last_revision = $revision;
        continue;
      }
      break;
    }

    if (empty($last_revision)) {
      return 0;
    }

    $last_revision = $storage->loadRevision($last_revision->content_entity_revision_id);

    // If there is no later revisions use current.
    if (empty($last_revision)) {
      $last_revision = $entity;
    }

    // Make sure loaded entity is in proper language.
    $last_revision = $last_revision->getTranslation($entity->language()->getId());

    $elapsed_time = $this->time->getRequestTime() - $last_revision->getRevisionCreationTime();

    return intval($elapsed_time / 86400);
  }

}
