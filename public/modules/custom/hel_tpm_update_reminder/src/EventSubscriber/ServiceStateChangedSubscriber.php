<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_update_reminder\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hel_tpm_update_reminder\UpdateReminderUserService;
use Drupal\hel_tpm_update_reminder\UpdateReminderUtility;
use Drupal\service_manual_workflow\Event\ServiceModerationEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Service state change event subscriber.
 */
class ServiceStateChangedSubscriber implements EventSubscriberInterface {

  use LoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Service for fetching service providers.
   *
   * @var \Drupal\hel_tpm_update_reminder\UpdateReminderUserService
   */
  protected UpdateReminderUserService $updateReminderUserService;

  /**
   * Logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a new ServiceStateChangedSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\hel_tpm_update_reminder\UpdateReminderUserService $update_reminder_user_service
   *   Service for fetching service providers.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, UpdateReminderUserService $update_reminder_user_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->updateReminderUserService = $update_reminder_user_service;
    $this->logger = $this->getLogger('hel_tpm_update_reminder');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'service_manual_workflow.draft.to.ready_to_publish' => ['clearMessagesSent'],
      'service_manual_workflow.draft.to.published' => ['clearMessagesSent'],
      'service_manual_workflow.ready_to_publish.to.ready_to_publish' => ['clearMessagesSent'],
      'service_manual_workflow.ready_to_publish.to.published' => ['clearMessagesSent'],
      'service_manual_workflow.published.to.ready_to_publish' => ['clearMessagesSent'],
      'service_manual_workflow.published.to.published' => ['clearMessagesSent'],
    ];
  }

  /**
   * Clear the past reminder information after saving the service.
   *
   * @param \Drupal\service_manual_workflow\Event\ServiceModerationEvent $event
   *   Service moderation event.
   *
   * @return void
   *   Void.
   */
  public function clearMessagesSent(ServiceModerationEvent $event): void {
    $state = $event->getModerationState();
    if (empty($entityTypeId = $state->content_entity_type_id?->value) ||
        empty($entityId = $state->content_entity_id?->value)) {
      return;
    }
    if ($entityTypeId !== 'node') {
      return;
    }

    if ($this->shouldClearOnSave((int) $entityId, $event->getAccount())) {
      UpdateReminderUtility::clearMessagesSent((int) $entityId);
    }
  }

  /**
   * Determines if node's past notification info should reset on save.
   *
   * @param int $entityId
   *   The ID of the node.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The account of the current user attempting to mark the node as checked.
   *
   * @return bool
   *   TRUE if notification info should be reset, FALSE otherwise.
   */
  protected function shouldClearOnSave(int $entityId, AccountProxyInterface $account): bool {
    $node = $this->entityTypeManager->getStorage('node')->load($entityId);

    try {
      $fetchedServiceProviderUpdaters = $this->updateReminderUserService->fetchServiceProviderUpdaters([$node->id()]);
    }
    catch (\Exception $e) {
      $fetchedServiceProviderUpdaters = [];
      $this->logger->error($this->t('Unable to fetch service provider updaters for node ID %node_id. Cannot clear past notification info on save.', [
        '%node_id' => $node->id(),
      ]));
    }
    $serviceProviderUpdaters = [];
    foreach ($fetchedServiceProviderUpdaters as $array) {
      if ($array['entity_id'] === $node->id()) {
        $serviceProviderUpdaters = $array['updaters'];
        break;
      }
    }

    if (in_array($account->id(), $serviceProviderUpdaters)) {
      return TRUE;
    }

    return FALSE;
  }

}
