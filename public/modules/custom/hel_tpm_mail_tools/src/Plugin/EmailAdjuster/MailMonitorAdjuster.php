<?php

namespace Drupal\hel_tpm_mail_tools\Plugin\EmailAdjuster;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\hel_tpm_mail_tools\Utility\MailMonitor;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\hel_tpm_mail_tools\Utility\MailMonitorNotification;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\symfony_mailer\Processor\EmailAdjusterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an email adjuster to log mail messages (Symfony Mailer 1.x).
 *
 * It logs mails sent via the Symfony Mailer module.
 *
 * @EmailAdjuster(
 *   id = "hel_tpm_mail_tools_mail_monitor ",
 *   label = @Translation("Mail Control"),
 *   description = @Translation("Mail Controller to monitor mail sending."),
 *   weight = 9998,
 * )
 */
class MailMonitorAdjuster extends EmailAdjusterBase implements ContainerFactoryPluginInterface {

  /**
   * Creates an email adjuster plugin for using Mail log via Symfony Mailer.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\hel_tpm_mail_tools\Utility\MailMonitor $mailMonitor
   *   The mail monitor service.
   * @param \Drupal\hel_tpm_mail_tools\Utility\MailMonitorNotification $mailMonitorNotification
   *   The mail monitor notification service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected MailMonitor $mailMonitor,
    protected MailMonitorNotification $mailMonitorNotification,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('hel_tpm_mail_tools.mail_monitor'),
      $container->get('hel_tpm_mail_tools.mail_monitor_notification')
    );
  }

  /**
   * Handles post-render operations for an email object.
   *
   * Performs additional checks on the email after it is rendered to ensure
   * compliance with sending limitations.
   *
   * @param \Drupal\hel_tpm_mail_tools\Utility\EmailInterface $email
   *   The email object being processed.
   *
   * @return void
   *   No value is returned, but a SkipMailException will be thrown if the
   *   mail has been sent too many times.
   */
  public function postRender(EmailInterface $email) {
    parent::postRender($email);
    if ($this->mailMonitor->mailSentTooManyTimes($email)) {
      $this->mailMonitorNotification->notifyAdministration($email);
      throw new SkipMailException('Mail sent too many times.');
    }
  }

}
