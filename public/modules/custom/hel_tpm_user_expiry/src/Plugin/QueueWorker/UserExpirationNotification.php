<?php

declare(strict_types = 1);

namespace Drupal\hel_tpm_user_expiry\Plugin\QueueWorker;

use Drupal;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\State\State;
use Drupal\group\Entity\GroupMembershipInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifier;
use Drupal\user\Entity\User;
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
   * Message notifier service.
   *
   * @var \Drupal\message_notify\MessageNotifier
   */
  protected $messageNotifier;

  /**
   * Password generator service.
   *
   * @var \Drupal\Core\Password\PasswordGeneratorInterface
   */
  protected $passwordGenerator;

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
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  private GroupMembershipLoaderInterface $groupMembershipLoader;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   Plugin id string.
   * @param array $plugin_definition
   *   Plugin definition array.
   * @param \Drupal\message_notify\MessageNotifier $message_notifier
   *   Message notifier service.
   * @param \Drupal\Core\Password\PasswordGeneratorInterface $password_generator
   *   Password generator service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MessageNotifier $message_notifier, PasswordGeneratorInterface $password_generator, State $state, GroupMembershipLoaderInterface $group_membership_loader) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $this->getLogger('hel_tpm_user_expiry');
    $this->messageNotifier = $message_notifier;
    $this->passwordGenerator = $password_generator;
    $this->state = $state;
    $this->groupMembershipLoader = $group_membership_loader;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('message_notify.sender'),
      $container->get('password_generator'),
      $container->get('state'),
      $container->get('group.membership_loader')
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

    // If user has been notified less than 2 times and last notification
    // has been sent in more.
    if (($count === 0 || $count === 1) && $this->getTimeLimit($count) >= $timestamp) {
      $user = User::load($this->getUid());
      if (!$user->isBlocked()) {
        // Send inactivity reminder.
        $this->sendNotification($this->getUid(), self::$reminderTemplates[$count]);
        $this->updateNotified();
      }
    }
    elseif ($count === 2 && $this->getTimeLimit($count) >= $timestamp) {
      // Deactivate user if last notification has been sent 2 days ago.
      if ($this->deactivateUser()) {
        $this->sendNotification($this->getUid(), self::$deactivatedTemplate);
        $this->updateNotified();
      }
    }
    elseif ($count === 3 && $this->getTimeLimit($count) >= $timestamp) {
      // Anonymize user if deactivation happened 30 days ago.
      if ($this->anonymizeUser()) {
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
   * @return bool
   *   TRUE if deactivating success, FALSE otherwise.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function deactivateUser(): bool {
    $user = User::load($this->getUid());
    if ($user->isBlocked()) {
      return FALSE;
    }
    $user->set('status', 0);
    $user->save();
    $this->logger->info('Deactivated %user', ['%user' => $user->id()]);
    return TRUE;
  }

  /**
   * Anonymize inactive and blocked user.
   *
   * @return bool
   *   TRUE when successful, FALSE otherwise.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  protected function anonymizeUser(): bool {
    $user = User::load($this->getUid());
    // Perform extra checks before anonymizing user data.
    if (!$user->isBlocked()
      || $user->get('access')->value >= strtotime('-210 days')
      || ($user->id() == 0 || $user->id() == 1)) {
      return FALSE;
    }

    // Anonymize user data.
    // Setting the email will also change the username.
    // See hel_tpm_general.module for more information.
    $user->setEmail('anonymous-' . $user->id() . '-' . random_int(100000, 999999) . '@anonymous.invalid');
    $user->setPassword($this->passwordGenerator->generate(20));
    $user->set('field_name', '');
    $user->set('field_job_title', '');
    $user->set('field_employer', '');
    foreach ($user->getRoles() as $role) {
      $user->removeRole($role);
    }

    if (count($user->validate())) {
      $this->logger->error('Anonymization of user %user failed for validation errors.', ['%user' => $user->id()]);
      return FALSE;
    }
    $user->save();

    // After anonymization remove user group memberships.
    $this->removeGroupMemberships($user);

    // Store anonymized user IDs using State API.
    if (is_array($anonymized_users = $this->state->get('hel_tpm_user_expiry.anonymized_users'))) {
      $anonymized_users[] = $user->id();
    }
    else {
      $anonymized_users = [$user->id()];
    }
    $this->state->set('hel_tpm_user_expiry.anonymized_users', $anonymized_users);

    $this->logger->info('Anonymized inactive and blocked user %user.', ['%user' => $user->id()]);
    return TRUE;
  }

  /**
   * Remove group memberships from user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *  User account interface.
   *
   * @return void
   *  Void.
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function removeGroupMemberships(AccountInterface $user) {
    $memberships = $this->groupMembershipLoader->loadByUser($user);
    foreach ($memberships as $membership) {
      $membership->getGroupRelationship()->delete();
    }
  }

  /**
   * Notification sending method.
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
   * @throws \Drupal\message_notify\Exception\MessageNotifyException
   */
  protected function sendNotification(int $uid, string $template): void {
    $message = Message::create([
      'template' => $template,
      'uid' => $uid,
    ]);
    $message->save();
    $this->messageNotifier->send($message);
    $this->logger->info('Sending expiry message %template to user %user', [
      '%user' => $uid,
      '%template' => $template,
    ]);
  }

}
