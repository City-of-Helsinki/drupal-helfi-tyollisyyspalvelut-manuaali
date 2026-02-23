<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_user_expiry\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\State;
use Drupal\hel_tpm_mail_tools\Utility\MessageSender;
use Drupal\hel_tpm_user_expiry\Anonymizer;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'hel_tpm_user_expiry_user_expiration_notification' queue worker.
 *
 * @QueueWorker(
 *   id = "hel_tpm_user_expiry_user_expiration_notification",
 *   title = @Translation("User expiration notification"),
 *   cron = {"time" = 60},
 * )
 */
final class UserExpirationNotification extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * Reminder message template names.
   *
   * @var string[]
   */
  protected static array $reminderTemplates = [
    0 => '1st_user_account_expiry_reminder',
    1 => '2nd_user_account_expiry_reminder',
  ];

  /**
   * Deactivated message template name.
   *
   * @var string
   */
  protected static string $deactivatedTemplate = 'hel_tpm_user_expiry_blocked';

  /**
   * Logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Message sender service.
   *
   * @var \Drupal\hel_tpm_mail_tools\Utility\MessageSender
   */
  protected $messageSender;

  /**
   * The state store.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * User id.
   *
   * @var int
   */
  private $uid;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Anonymizer service.
   *
   * @var \Drupal\hel_tpm_user_expiry\Anonymizer
   */
  private Anonymizer $anonymizer;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   Plugin id string.
   * @param array $plugin_definition
   *   Plugin definition array.
   * @param \Drupal\hel_tpm_mail_tools\Utility\MessageSender $message_sender
   *   Message notifier service.
   * @param \Drupal\Core\State\State $state
   *   State service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\hel_tpm_user_expiry\Anonymizer $anonymizer
   *   User anonymizer service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MessageSender $message_sender,
    State $state,
    EntityTypeManagerInterface $entity_type_manager,
    Anonymizer $anonymizer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $this->getLogger('hel_tpm_user_expiry');
    $this->messageSender = $message_sender;
    $this->state = $state;
    $this->anonymizer = $anonymizer;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('hel_tpm_mail_tools.utility.message_sender'),
      $container->get('state'),
      $container->get('entity_type.manager'),
      $container->get('hel_tpm_user_expiry.anonymizer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $this->setUid((int) $data->uid);
    $notified = $this->getNotified();
    $count = (int) $notified['count'];
    $timestamp = $notified['timestamp'];
    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager->getStorage('user')->load($this->getUid());

    // If user has been notified less than 2 times and last notification
    // has been sent in more.
    if (($count === 0 || $count === 1) && $this->getTimeLimit($count) >= $timestamp) {
      // Send inactivity reminder and mark that user has been notified.
      if (!$user->isBlocked() && $this->sendNotification(self::$reminderTemplates[$count], $user)) {
        $this->updateNotified();
      }
    }
    // If previous notifications has been sent and last notification has been
    // sent at least 2 days ago.
    elseif ($count === 2 && $this->getTimeLimit($count) >= $timestamp) {
      // Send deactivate notification and deactivate user.
      if (!$user->isBlocked() && $this->sendNotification(self::$deactivatedTemplate, $user)) {
        $this->deactivateUser($user);
        $this->updateNotified();
      }
    }
    elseif ($count === 3 && $this->getTimeLimit($count) >= $timestamp) {
      // Anonymize user if deactivation happened 30 days ago.
      if ($this->anonymizer->anonymizeUser($user)) {
        $this->updateNotified();
      }
    }
  }

  /**
   * Get time limit for notifications.
   *
   * @param int $count
   *   Count messages have been sent.
   *
   * @return int
   *   Time limit in unix time.
   */
  protected function getTimeLimit(int $count): int {
    $limits = [
      // Send first notification immediately.
      0 => 0,
      // Time since first notification.
      1 => strtotime('-2 weeks'),
      // Time since second notification, deactivation.
      2 => strtotime('-2 days'),
      // Time since deactivation, user is anonymized.
      3 => strtotime('-30 days'),
    ];
    return $limits[$count];
  }

  /**
   * Setter for uid.
   *
   * @param int $uid
   *   User id.
   *
   * @return void
   *   -
   */
  protected function setUid(int $uid): void {
    $this->uid = $uid;
  }

  /**
   * Getter for uid.
   *
   * @return int
   *   -
   */
  protected function getUid(): int {
    return $this->uid;
  }

  /**
   * Update notification state.
   *
   * @return void
   *   -
   */
  protected function updateNotified(): void {
    $notified = $this->getNotified();
    $notified['count']++;
    $notified['timestamp'] = \Drupal::time()->getRequestTime();
    $this->state->set($this->getStateName(), $notified);
  }

  /**
   * Getter for notified state.
   *
   * @return array
   *   Notified state array.
   */
  protected function getNotified(): array {
    $notified = $this->state->get($this->getStateName());
    if (empty($notified)) {
      return ['count' => 0, 'timestamp' => 0];
    }
    return $notified;
  }

  /**
   * Helper method to get state name.
   *
   * @return string
   *   State name string.
   */
  protected function getStateName(): string {
    return 'hel_tpm_user_expiry.notified.' . $this->getUid();
  }

  /**
   * Delete state method.
   *
   * @return void
   *   -
   */
  protected function deleteState(): void {
    $this->state->delete($this->getStateName());
  }

  /**
   * Deactivate user.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user to be deactivated.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function deactivateUser(UserInterface $user): void {
    $user->set('status', 0);
    $user->save();
    $this->logger->info('Deactivated user with user ID %user_id.', ['%user_id' => $user->id()]);
  }

  /**
   * Notification sending method.
   *
   * @param string $template
   *   The name of the template.
   * @param \Drupal\user\Entity\User $account
   *   The user to receive the message.
   *
   * @return bool
   *   TRUE if mail is sent, FALSE otherwise.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\message_notify\Exception\MessageNotifyException
   */
  protected function sendNotification(string $template, UserInterface $account): bool {
    $isSent = $this->messageSender->createAndSend($template, $account, []);

    if ($isSent) {
      $this->logger->info('Expiry message %template to user %user has been sent.', [
        '%user' => $account->id(),
        '%template' => $template,
      ]);
    }
    else {
      $this->logger->notice('Expiry message %template to user %user has not been sent.', [
        '%user' => $account->id(),
        '%template' => $template,
      ]);
    }

    return $isSent;
  }

}
