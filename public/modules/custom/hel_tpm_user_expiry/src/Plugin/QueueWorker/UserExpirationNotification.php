<?php

declare(strict_types = 1);

namespace Drupal\hel_tpm_user_expiry\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifier;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
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
   * Template mapping.
   *
   * @var string[]
   */
  protected static $templates = [
    0 => '1st_user_account_expiry_reminder',
    1 => '2nd_user_account_expiry_reminder',
  ];

  /**
   * Logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Message notifier service.
   *
   * @var \Drupal\message_notify\MessageNotifier
   */
  protected $messageNotifier;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   Plugin id string.
   * @param array $plugin_definition
   *   Plugin definition array.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger service.
   * @param \Drupal\message_notify\MessageNotifier $messageNotifier
   *   Message notifier service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MessageNotifier $messageNotifier) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $this->getLogger('hel_tpm_user_expiry');
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
    $this->setUid($data->uid);
    $notified = $this->getNotified();
    $count = $notified['count'];
    $timestamp = $notified['timestamp'];

    // If user has been notified less than 2 times and last notification
    // has been sent in more.
    if ($count < 2 && $this->getTimeLimit($count) >= $timestamp) {
      $this->sendNotification($this->getUid(), self::$templates[$count]);
      $this->updateNotified();
      return;
    }

    if ($timestamp >= $this->getTimeLimit(3)) {
      // Deactivate user if last notification has been sent 5 days ago.
      $this->deactivateUser();
      // Delete state after we have queued user for deactivation.
      // Prevents continous deactivation if account is activated by hand
      // until user has been notified again.
      $this->deleteState();
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
  protected function getTimeLimit($count) {
    $limits = [
    // Send first notification immediately.
      0 => 0,
    // Time since first notification.
      1 => strtotime('-2 weeks'),
    // Time since second notification, deactivation.
      3 => strtotime('-5 days'),
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
  protected function setUid($uid) {
    $this->uid = $uid;
  }

  /**
   * Getter for uid.
   *
   * @return int|null
   *   -
   */
  protected function getUid() {
    return $this->uid;
  }

  /**
   * Update notification state.
   *
   * @return void
   *   -
   */
  protected function updateNotified() {
    $notified = $this->getNotified();
    $notified['count']++;
    $notified['timestamp'] = \Drupal::time()->getRequestTime();
    \Drupal::state()->set($this->getStateName(), $notified);
  }

  /**
   * Getter for notified state.
   *
   * @return array
   *   Notified state array.
   */
  protected function getNotified() {
    $notified = \Drupal::state()->get($this->getStateName());
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
  protected function getStateName() {
    return 'hel_tpm_user_expiry.notified.' . $this->getUid();
  }

  /**
   * Delete state method.
   *
   * @return void
   *   -
   */
  protected function deleteState() {
    \Drupal::state()->delete($this->getStateName());
  }

  /**
   * Deactivate user.
   */
  protected function deactivateUser() {
    $user = User::load($this->getUid());
    $user->set('status', 0);
    $user->save();
    $this->logger->info('Deactivated %user', ['%user' => $user->id()]);
  }

  /**
   * Notification sending methdo.
   *
   * @param int $uid
   *   User id who message is sent.
   * @param string $template
   *   Name of message template.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function sendNotification($uid, $template) {
    $message = Message::create([
      'template' => $template,
      'uid' => $uid,
    ]);
    $message->save();
    $this->messageNotifier->send($message);
    $this->logger->info('Sending notification %template to user %user', [
      '%user' => $uid,
      '%template' => $template,
    ]);
  }

}
