<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_mail_tools\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hel_tpm_mail_tools\Plugin\EmailAdjuster\MailMonitorAdjuster;
use Drupal\hel_tpm_mail_tools\Utility\MailMonitor;
use Drupal\hel_tpm_mail_tools\Utility\MailMonitorNotification;
use Drupal\KernelTests\KernelTestBase;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;

/**
 * Tests the mail monitor email adjuster.
 *
 * @group hel_tpm_mail_tools
 */
final class MailMonitorAdjusterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'message',
    'message_notify',
    'symfony_mailer',
    'symfony_mailer_log',
    'hel_tpm_mail_tools',
  ];

  /**
   * Tests that normal mail is not blocked.
   */
  public function testPostRenderAllowsMailWhenLimitIsNotExceeded(): void {
    $email = $this->createMock(EmailInterface::class);

    $mail_monitor = $this->createMock(MailMonitor::class);
    $mail_monitor
      ->expects($this->once())
      ->method('mailSentTooManyTimes')
      ->with($email)
      ->willReturn(FALSE);

    $mail_monitor_notification = $this->createMock(MailMonitorNotification::class);
    $mail_monitor_notification
      ->expects($this->never())
      ->method('notifyAdministration');

    $adjuster = $this->createAdjuster($mail_monitor, $mail_monitor_notification);

    $adjuster->postRender($email);
  }

  /**
   * Tests that excessive mail is reported and blocked.
   */
  public function testPostRenderNotifiesAdministrationAndBlocksMailWhenLimitIsExceeded(): void {
    $email = $this->createMock(EmailInterface::class);

    $mail_monitor = $this->createMock(MailMonitor::class);
    $mail_monitor
      ->expects($this->once())
      ->method('mailSentTooManyTimes')
      ->with($email)
      ->willReturn(TRUE);

    $mail_monitor_notification = $this->createMock(MailMonitorNotification::class);
    $mail_monitor_notification
      ->expects($this->once())
      ->method('notifyAdministration')
      ->with($email)
      ->willReturn(TRUE);

    $adjuster = $this->createAdjuster($mail_monitor, $mail_monitor_notification);

    $this->expectException(SkipMailException::class);
    $this->expectExceptionMessage('Mail sent too many times.');

    $adjuster->postRender($email);
  }

  /**
   * Tests that the adjuster can be created from the container.
   */
  public function testCreateReturnsMailMonitorAdjusterInstance(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('symfony_mailer_log');

    $adjuster = MailMonitorAdjuster::create(
      $this->container,
      [],
      'hel_tpm_mail_tools_mail_monitor',
      []
    );

    $this->assertInstanceOf(MailMonitorAdjuster::class, $adjuster);
  }

  /**
   * Creates a mail monitor adjuster.
   *
   * @param \Drupal\hel_tpm_mail_tools\Utility\MailMonitor $mail_monitor
   *   The mail monitor service.
   * @param \Drupal\hel_tpm_mail_tools\Utility\MailMonitorNotification $mail_monitor_notification
   *   The mail monitor notification service.
   *
   * @return \Drupal\hel_tpm_mail_tools\Plugin\EmailAdjuster\MailMonitorAdjuster
   *   The mail monitor adjuster.
   */
  private function createAdjuster(
    MailMonitor $mail_monitor,
    MailMonitorNotification $mail_monitor_notification,
  ): MailMonitorAdjuster {
    return new MailMonitorAdjuster(
      [],
      'hel_tpm_mail_tools_mail_monitor',
      [],
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      $mail_monitor,
      $mail_monitor_notification,
    );
  }

}