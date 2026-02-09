<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_update_reminder\Plugin\QueueWorker;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hel_tpm_general\PreventMailUtility;
use Drupal\hel_tpm_update_reminder\UpdateReminderUtility;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifier;
use Drupal\node\NodeInterface;
use Drupal\service_manual_workflow\ModerationTransition;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
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

  use StringTranslationTrait;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

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
   * Service's node id.
   *
   * @var int
   */
  protected int $serviceId;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Admin user entity.
   *
   * @var \Drupal\user\UserInterface
   */
  private UserInterface $adminUser;

  /**
   * Moderation transition entity.
   *
   * @var \Drupal\service_manual_workflow\ModerationTransition
   */
  private ModerationTransition $moderationTransition;

  /**
   * Constructs a new instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID of the instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\message_notify\MessageNotifier $message_notifier
   *   The message notifier service.
   * @param \Drupal\Component\Datetime\Time $time
   *   The time service.
   * @param \Drupal\service_manual_workflow\ModerationTransition $moderation_transition
   *   The moderation transition service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    MessageNotifier $message_notifier,
    Time $time,
    ModerationTransition $moderation_transition,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->messageNotifier = $message_notifier;
    $this->time = $time;
    $this->moderationTransition = $moderation_transition;
    $this->logger = $this->getLogger('hel_tpm_update_reminder');
    $this->adminUser = $this->entityTypeManager->getStorage('user')->load(1);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('message_notify.sender'),
      $container->get('datetime.time'),
      $container->get('service_manual_workflow.moderation_transition')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!is_int($data)) {
      return;
    }
    $this->serviceId = $data;

    // Do nothing if node is not checked, e.g. there has been no state
    // transitions as defined in event subscriber ServiceStateChangedSubscriber.
    if (empty($checked = UpdateReminderUtility::getCheckedTimestamp($this->serviceId))) {
      return;
    }
    // Only continue processing node if checked timestamp is smaller (older)
    // than the first reminder limit.
    if ($checked >= UpdateReminderUtility::getFirstLimitTimestamp()) {
      return;
    }
    $sent = UpdateReminderUtility::getMessagesSent($this->serviceId);
    $reminded = UpdateReminderUtility::getRemindedTimestamp($this->serviceId);

    match (TRUE) {
      ($sent === 0) => $this->remind(1),
      ($sent === 1) && isset($reminded) && ($reminded < UpdateReminderUtility::getSecondLimitTimestamp()) => $this->remind(2),
      ($sent === 2) && isset($reminded) && ($reminded < UpdateReminderUtility::getThirdLimitTimestamp()) => $this->outdate(),
      default => FALSE,
    };
  }

  /**
   * Remind service provider update user and updates related node state.
   *
   * @param int $messageNumber
   *   The sequence number of the reminder going to be sent.
   *
   * @return bool
   *   Denoting success or failure of sending the reminder message.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\message_notify\Exception\MessageNotifyException
   */
  protected function remind(int $messageNumber): bool {
    // Do not proceed if sending reminder mail is blocked by settings.
    if (PreventMailUtility::isUpdateReminderBlocked()) {
      return FALSE;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\node\NodeInterface $service */
    if (empty($service = $storage->load($this->serviceId))) {
      return FALSE;
    }

    if (empty($service?->get('field_service_provider_updatee')?->entity)) {
      return FALSE;
    }
    $account = $service->get('field_service_provider_updatee')->entity;

    $template = match ($messageNumber) {
      1 => 'hel_tpm_update_reminder_service',
      2 => 'hel_tpm_update_reminder_service2',
      default => FALSE,
    };
    if (empty($template)) {
      return FALSE;
    }

    $isReminded = $this->sendMessage($template, $account, $service);
    if ($isReminded === TRUE) {
      UpdateReminderUtility::setMessagesSentState($this->serviceId, $messageNumber);
    }
    return $isReminded;
  }

  /**
   * Mark service as outdated, informs users and updates related node state.
   *
   * @return bool
   *   Denoting success or failure.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\message_notify\Exception\MessageNotifyException
   */
  protected function outdate(): bool {
    // Do not proceed if sending outdated mail is blocked by settings.
    if (PreventMailUtility::isUpdateReminderOutdatedBlocked()) {
      return FALSE;
    }

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\node\NodeInterface $service */
    if (empty($service = $storage->load($this->serviceId))) {
      return FALSE;
    }

    if (!$service->isLatestRevision()) {
      $vid = $storage->getLatestRevisionId($service->id());
      $service = $storage->loadRevision($vid);
    }

    $serviceProviderInformed = FALSE;
    if (!empty($service?->get('field_service_provider_updatee')?->entity)) {
      $serviceProviderAccount = $service->get('field_service_provider_updatee')->entity;
      $serviceProviderInformed = $this->sendMessage('hel_tpm_update_reminder_outdated', $serviceProviderAccount, $service);
    }

    $responsibleInformed = FALSE;
    if (!empty($service?->get('field_responsible_updatee')?->entity)) {
      $responsibleAccount = $service->get('field_responsible_updatee')->entity;
      $responsibleInformed = $this->sendMessage('hel_tpm_update_reminder_outdated', $responsibleAccount, $service);
    }

    if ($serviceProviderInformed || $responsibleInformed) {
      if (!$service->isDefaultTranslation()) {
        $service = $service->getTranslation('x-default');
      }
      $this->moderationTransition->setServiceOutdated($service, 'Outdated by update reminder');

      $this->logger->info('Service "%service_title" (ID: %service_id) automatically marked as outdated.', [
        '%service_title' => $service->getTitle(),
        '%service_id' => $this->serviceId,
      ]);
      UpdateReminderUtility::setMessagesSentState($this->serviceId, 3);
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
   * @param \Drupal\node\NodeInterface $service
   *   The service node attached to message.
   *
   * @return bool
   *   Boolean value denoting success or failure of sending the message.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\message_notify\Exception\MessageNotifyException
   */
  protected function sendMessage(string $template, User $account, NodeInterface $service): bool {
    if ($account->isBlocked()) {
      return FALSE;
    }
    $message = Message::create([
      'template' => $template,
      'uid' => $account->id(),
    ]);
    $message->set('field_node', $service);
    $message->set('field_user', $account);
    $message->save();
    // Prevent sending all the mails at once.
    sleep(1);
    return $this->messageNotifier->send($message);
  }

}
