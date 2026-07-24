<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_mail_tools\Kernel;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Tests the mail monitor administration notification service.
 *
 * @group hel_tpm_mail_tools
 */
final class MailMonitorNotificationTest extends KernelTestBase {

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
   * Captured test mails.
   *
   * @var array<int, array<string, mixed>>
   */
  private array $mails = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);

    $this->config('system.site')
      ->set('mail', 'admin@example.com')
      ->set('langcode', 'en')
      ->save();

    $this->container->set('plugin.manager.mail', new class($this->mails) implements MailManagerInterface {

      /**
       * Constructs the test mail manager.
       *
       * @param array<int, array<string, mixed>> $mails
       *   Captured mails.
       */
      public function __construct(private array &$mails) {
      }

      /**
       * {@inheritdoc}
       */
      public function mail($module, $key, $to, $langcode, $params = [], $reply = NULL, $send = TRUE) {
        $message = [
          'module' => $module,
          'key' => $key,
          'to' => $to,
          'langcode' => $langcode,
          'params' => $params,
          'reply-to' => $reply,
          'send' => $send,
          'subject' => '',
          'body' => [],
        ];

        hel_tpm_mail_tools_mail($key, $message, $params);

        $message['result'] = TRUE;
        $this->mails[] = $message;

        return $message;
      }

      /**
       * {@inheritdoc}
       */
      public function getInstance(array $options) {
        return NULL;
      }

    });
  }

  /**
   * Tests that an abnormality notification email is sent to site email.
   */
  public function testNotifyAdministrationSendsNotification(): void {
    $email = $this->createEmail();

    $result = $this->container
      ->get('hel_tpm_mail_tools.mail_monitor_notification')
      ->notifyAdministration($email);

    $this->assertTrue($result);
    $this->assertCount(1, $this->mails);

    $mail = reset($this->mails);
    $this->assertSame('hel_tpm_mail_tools', $mail['module']);
    $this->assertSame('mail_monitor_abnormality', $mail['key']);
    $this->assertSame('admin@example.com', $mail['to']);
    $this->assertSame('Mail abnormality detected', (string) $mail['subject']);

    $body = implode("\n", array_map('strval', $mail['body']));
    $this->assertStringContainsString('Mail Monitor detected abnormal mail activity and blocked an email.', $body);
    $this->assertStringContainsString('Type: test_type', $body);
    $this->assertStringContainsString('Subtype: test_sub_type', $body);
    $this->assertStringContainsString('Subject: Test subject', $body);
    $this->assertStringContainsString('Recipients: recipient@example.com', $body);
  }

  /**
   * Tests that repeated notifications for the same abnormality are throttled.
   */
  public function testNotifyAdministrationThrottlesSameAbnormality(): void {
    $notification = $this->container->get('hel_tpm_mail_tools.mail_monitor_notification');
    $email = $this->createEmail();

    $this->assertTrue($notification->notifyAdministration($email));
    $this->assertFalse($notification->notifyAdministration($email));

    $this->assertCount(1, $this->mails);
  }

  /**
   * Tests that different abnormalities are notified independently.
   */
  public function testNotifyAdministrationSendsNotificationForDifferentAbnormality(): void {
    $notification = $this->container->get('hel_tpm_mail_tools.mail_monitor_notification');

    $this->assertTrue($notification->notifyAdministration($this->createEmail()));
    $this->assertTrue($notification->notifyAdministration($this->createEmail([
      'subject' => 'Another test subject',
    ])));

    $this->assertCount(2, $this->mails);
  }

  /**
   * Tests that no notification is sent when site email is missing.
   */
  public function testNotifyAdministrationReturnsFalseWhenSiteMailIsMissing(): void {
    $this->config('system.site')
      ->clear('mail')
      ->save();

    $result = $this->container
      ->get('hel_tpm_mail_tools.mail_monitor_notification')
      ->notifyAdministration($this->createEmail());

    $this->assertFalse($result);
    $this->assertCount(0, $this->mails);
  }

  /**
   * Creates a mocked Symfony Mailer email.
   *
   * @param array $values
   *   Optional email values.
   *
   * @return \Drupal\symfony_mailer\EmailInterface
   *   The mocked email.
   */
  private function createEmail(array $values = []): EmailInterface {
    $values += [
      'type' => 'test_type',
      'sub_type' => 'test_sub_type',
      'subject' => 'Test subject',
      'text_body' => 'Test body',
      'recipients' => ['recipient@example.com'],
    ];

    $email = $this->createMock(EmailInterface::class);
    $email->method('getType')->willReturn($values['type']);
    $email->method('getSubType')->willReturn($values['sub_type']);
    $email->method('getSubject')->willReturn($values['subject']);
    $email->method('getTextBody')->willReturn($values['text_body']);
    $email->method('getTo')->willReturn(array_map(
      static fn (string $address): object => new class($address) {

        /**
         * Constructs a test address object.
         *
         * @param string $email
         *   The email address.
         */
        public function __construct(private readonly string $email) {
        }

        /**
         * Gets the email address.
         *
         * @return string
         *   The email address.
         */
        public function getEmail(): string {
          return $this->email;
        }

      },
      $values['recipients'],
    ));

    return $email;
  }

}