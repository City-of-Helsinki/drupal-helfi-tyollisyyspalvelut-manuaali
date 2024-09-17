<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_stats\EventSubscriber;

use Drupal\hel_tpm_service_stats\RevisionHistoryService;
use Drupal\service_manual_workflow\Event\ServiceModerationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Service stats subscriber.
 */
final class HelTpmServiceStatsSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a HelTpmServiceStatsSubscriber object.
   */
  public function __construct(
    private readonly RevisionHistoryService $helTpmServiceStatsRevisionHistory,
  ) {}

  /**
   * Kernel request event handler.
   */
  public function readyToPublishToPublished(ServiceModerationEvent $event): void {
    $state = $event->getModerationState();
    $langcode = $state->language()->getId();
    $published_row = $this->helTpmServiceStatsRevisionHistory->getPublishedRevisionRow($state->content_entity_revision_id->value, $langcode);
    if (empty($published_row)) {
      return;
    }
    $published_row = reset($published_row);
    $previous_revision = $this->helTpmServiceStatsRevisionHistory->getPreviousRevision($published_row);
    $this->helTpmServiceStatsRevisionHistory->createServicePublishedRow($published_row, $previous_revision);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'service_manual_workflow.ready_to_publish.to.published' => ['readyToPublishToPublished'],
    ];
  }

}
