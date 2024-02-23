<?php

declare(strict_types = 1);

namespace Drupal\hel_tpm_update_reminder\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\hel_tpm_update_reminder\UpdateReminderUtility;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifier;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'hel_tpm_update_reminder_service' queue worker.
 *
 * @QueueWorker(
 *   id = "hel_tpm_update_reminder_service",
 *   title = @Translation("Service update reminder"),
 *   cron = {"time" = 60},
 * )
 */
final class ServiceUpdateReminder extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * Logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Message notifier service.
   *
   * @var \Drupal\message_notify\MessageNotifier
   */
  protected MessageNotifier $messageNotifier;

  /**
   * Service node.
   *
   * @var \Drupal\node\NodeInterface
   */
  private NodeInterface $service;

  /**
   * Service id.
   *
   * @var int
   */
  private int $serviceId;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   Plugin id string.
   * @param array $plugin_definition
   *   Plugin definition array.
   * @param \Drupal\message_notify\MessageNotifier $messageNotifier
   *   Message notifier service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MessageNotifier $messageNotifier) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $this->getLogger('hel_tpm_update_reminder');
    $this->messageNotifier = $messageNotifier;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('message_notify.sender')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!$data instanceof NodeInterface) {
      return;
    }
    $this->service = $data;
    $this->serviceId = (int) $this->service->id();

    if (empty($changed = UpdateReminderUtility::getChecked($this->serviceId))) {
      return;
    }
    $sent = UpdateReminderUtility::getMessagesSent($this->serviceId);

    match (TRUE) {
      ($sent === 0) && ($changed < UpdateReminderUtility::getFirstServiceLimit()) => $this->remind(1),
      ($sent === 1) && ($changed < UpdateReminderUtility::getSecondServiceLimit()) => $this->remind(2),
      ($sent === 2) && ($changed < UpdateReminderUtility::getThirdServiceLimit()) => $this->outdate(),
      default => FALSE,
    };
  }

  /**
   * Reminds service provider update user and updates message counter.
   *
   * @param int $messageNumber
   *   The sequence number of the reminder going to be sent.
   *
   * @return bool
   *   Denoting success or failure of sending the reminder message.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\message_notify\Exception\MessageNotifyException
   */
  protected function remind(int $messageNumber): bool {
    if (empty($this->service?->get('field_service_provider_updatee')?->entity)) {
      return FALSE;
    }
    $account = $this->service->get('field_service_provider_updatee')->entity;

    UpdateReminderUtility::setMessagesSent($this->serviceId, $messageNumber);
    return $this->sendMessage('hel_tpm_update_reminder_service', $account);
  }

  /**
   * Marks service as outdated and updates message counter.
   *
   * @return bool
   *   Denoting success or failure.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\message_notify\Exception\MessageNotifyException
   */
  protected function outdate(): bool {
    $serviceProviderInformed = FALSE;
    if (!empty($this->service?->get('field_service_provider_updatee')?->entity)) {
      $serviceProviderAccount = $this->service->get('field_service_provider_updatee')->entity;
      $serviceProviderInformed = $this->sendMessage('hel_tpm_update_reminder_outdated', $serviceProviderAccount);
    }

    $responsibleInformed = FALSE;
    if (!empty($this->service?->get('field_responsible_updatee')?->entity)) {
      $responsibleAccount = $this->service->get('field_responsible_updatee')->entity;
      $responsibleInformed = $this->sendMessage('hel_tpm_update_reminder_outdated', $responsibleAccount);
    }

    if ($serviceProviderInformed || $responsibleInformed) {
      $this->service->set('moderation_state', 'outdated');
      $this->service->save();
      $this->logger->info('Service "%service_title" (ID: %service_id) automatically marked as outdated.', [
        '%service_title' => $this->service->getTitle(),
        '%service_id' => $this->serviceId,
      ]);
      UpdateReminderUtility::setMessagesSent($this->serviceId, 3);
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Creates and sends a message.
   *
   * @param string $template
   *   The name of the template.
   * @param \Drupal\user\Entity\User $account
   *   The user to receive the message.
   *
   * @return bool
   *   Boolean value denoting success or failure of sending the message.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\message_notify\Exception\MessageNotifyException
   */
  protected function sendMessage(string $template, User $account): bool {
    $message = Message::create([
      'template' => $template,
      'uid' => $account->id(),
    ]);
    $message->set('field_node', $this->service);
    $message->set('field_user', $account);
    $message->save();
    return $this->messageNotifier->send($message);
  }

}
