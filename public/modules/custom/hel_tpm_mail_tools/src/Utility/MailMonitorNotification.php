<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_mail_tools\Utility;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\symfony_mailer\EmailInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides administration notifications for mail monitor abnormalities.
 */
class MailMonitorNotification {
  use StringTranslationTrait;

  /**
   * State key prefix for sent abnormality notifications.
   */
  private const STATE_KEY_PREFIX = 'hel_tpm_mail_tools.mail_monitor_notification.';

  /**
   * Notification throttle window in seconds.
   */
  private const NOTIFICATION_INTERVAL = 3600;

  /**
   * Constructs a new MailMonitorNotification service.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state API service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    protected StateInterface $state,
    protected MailManagerInterface $mailManager,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Notifies administration about detected abnormal mail activity.
   *
   * Notifications for the same abnormality are throttled with
   * State API to avoid repeatedly sending administration emails
   * for the same blocked mail.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The abnormal email.
   *
   * @return bool
   *   TRUE when notification was sent, FALSE otherwise.
   */
  public function notifyAdministration(EmailInterface $email): bool {
    if (!$this->shouldNotify($email)) {
      return FALSE;
    }

    $site_config = $this->configFactory->get('system.site');
    $to = $site_config->get('mail');

    if (empty($to)) {
      $this->logger->warning('Mail monitor abnormality notification was not sent because site email is not configured.');
      return FALSE;
    }

    $params = [
      'subject' => $email->getSubject(),
      'type' => $email->getType(),
      'sub_type' => $email->getSubType(),
      'recipients' => $this->getRecipients($email),
    ];

    $result = $this->mailManager->mail(
          'hel_tpm_mail_tools',
          'mail_monitor_abnormality',
          $to,
          $site_config->get('langcode') ?: 'en',
          $params
      );

    if (!empty($result['result'])) {
      $this->markNotified($email);
      return TRUE;
    }

    $this->logger->error('Mail monitor abnormality notification could not be sent.');
    return FALSE;
  }

  /**
   * Determines whether administration should be notified.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The abnormal email.
   *
   * @return bool
   *   TRUE if notification should be sent, FALSE otherwise.
   */
  protected function shouldNotify(EmailInterface $email): bool {
    $last_notification_time = (int) $this->state->get($this->getStateKey($email), 0);

    return $last_notification_time + self::NOTIFICATION_INTERVAL <= time();
  }

  /**
   * Marks this abnormality as notified.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The abnormal email.
   */
  protected function markNotified(EmailInterface $email): void {
    $this->state->set($this->getStateKey($email), time());
  }

  /**
   * Gets the state key for this email abnormality.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The abnormal email.
   *
   * @return string
   *   The state key.
   */
  protected function getStateKey(EmailInterface $email): string {
    return self::STATE_KEY_PREFIX . hash('sha256', implode('|', [
      $email->getType(),
      $email->getSubType() ?? '',
      $email->getSubject(),
      $email->getTextBody(),
      implode(',', $this->getRecipients($email)),
    ]));
  }

  /**
   * Gets recipient email addresses from an email object.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email object.
   *
   * @return array
   *   Recipient email addresses.
   */
  protected function getRecipients(EmailInterface $email): array {
    $recipients = [];

    foreach ($email->getTo() as $address) {
      $recipients[] = $address->getEmail();
    }

    return $recipients;
  }

}
