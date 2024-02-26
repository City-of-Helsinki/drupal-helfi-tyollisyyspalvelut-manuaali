<?php

declare(strict_types = 1);

namespace Drupal\hel_tpm_update_reminder\EventSubscriber;

use Drupal\hel_tpm_update_reminder\UpdateReminderUtility;
use Drupal\service_manual_workflow\Event\ServiceModerationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Service state change event subscriber.
 */
class ServiceStateChangedSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'service_manual_workflow.draft.to.ready_to_publish' => ['markContentChecked'],
      'service_manual_workflow.draft.to.published' => ['markContentChecked'],
      'service_manual_workflow.ready_to_publish.to.ready_to_publish' => ['markContentChecked'],
      'service_manual_workflow.ready_to_publish.to.published' => ['markContentChecked'],
      'service_manual_workflow.published.to.ready_to_publish' => ['markContentChecked'],
      'service_manual_workflow.published.to.published' => ['markContentChecked'],
    ];
  }

  /**
   * Mark service content checked after transition.
   *
   * @param \Drupal\service_manual_workflow\Event\ServiceModerationEvent $event
   *   Service moderation event.
   *
   * @return void
   *   Void.
   */
  public function markContentChecked(ServiceModerationEvent $event): void {
    $state = $event->getModerationState();
    if (empty($entityTypeId = $state->content_entity_type_id?->value) ||
        empty($entityId = $state->content_entity_id?->value)) {
      return;
    }

    if ($entityTypeId === 'node') {
      UpdateReminderUtility::markAsChecked((int) $entityId);
    }
  }

}
