<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_update_reminder\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\hel_tpm_update_reminder\UpdateReminderUtility;
use Drupal\service_manual_workflow\Event\ServiceModerationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Service state change event subscriber.
 */
class ServiceStateChangedSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ServiceStateChangedSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'service_manual_workflow.draft.to.ready_to_publish' => ['markNodeChecked'],
      'service_manual_workflow.draft.to.published' => ['markNodeChecked'],
      'service_manual_workflow.ready_to_publish.to.ready_to_publish' => ['markNodeChecked'],
      'service_manual_workflow.ready_to_publish.to.published' => ['markNodeChecked'],
      'service_manual_workflow.published.to.ready_to_publish' => ['markNodeChecked'],
      'service_manual_workflow.published.to.published' => ['markNodeChecked'],
    ];
  }

  /**
   * Mark node checked after transition.
   *
   * @param \Drupal\service_manual_workflow\Event\ServiceModerationEvent $event
   *   Service moderation event.
   *
   * @return void
   *   Void.
   */
  public function markNodeChecked(ServiceModerationEvent $event): void {
    $state = $event->getModerationState();
    if (empty($entityTypeId = $state->content_entity_type_id?->value) ||
        empty($entityId = $state->content_entity_id?->value)) {
      return;
    }

    if ($entityTypeId === 'node') {
      if ($this->canMarkChecked((int) $entityId, $event->getAccount())) {
        UpdateReminderUtility::markNodeChecked((int) $entityId);
      }
    }
  }

  /**
   * Determines if a node can be marked as checked.
   *
   * @param int $entityId
   *   The ID of the node entity to be checked.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The account of the current user attempting to mark the node as checked.
   *
   * @return bool
   *   TRUE if the node can be marked as checked by the current account.
   */
  protected function canMarkChecked(int $entityId, AccountProxyInterface $account): bool {
    $node = $this->entityTypeManager->getStorage('node')->load($entityId);
    $serviceProviderUpdater = $node->field_service_provider_updatee->entity;

    if (empty($serviceProviderUpdater)) {
      return FALSE;
    }

    if ($serviceProviderUpdater->id() === $account->id()) {
      return TRUE;
    }

    return FALSE;
  }

}
