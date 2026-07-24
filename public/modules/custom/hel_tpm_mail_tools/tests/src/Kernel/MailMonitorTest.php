<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_mail_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Tests the mail monitor service.
 *
 * @group hel_tpm_mail_tools
 */
final class MailMonitorTest extends KernelTestBase {

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
   * Symfony mailer log storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $logStorage;

  /**
   * The mail monitor service.
   *
   * @var \Drupal\hel_tpm_mail_tools\Utility\MailMonitor
   */
  private $mailMonitor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('symfony_mailer_log');

    $this->logStorage = $this->container
      ->get('entity_type.manager')
      ->getStorage('symfony_mailer_log');

    $this->mailMonitor = $this->container
      ->get('hel_tpm_mail_tools.mail_monitor');
  }

  /**
   * Tests that fewer than five identical emails are not considered excessive.
   */
  public function testMailSentTooManyTimesReturnsFalseBelowLimit(): void {
    $email = $this->createEmail();

    $this->createLogItems(4);

    $this->assertFalse($this->mailMonitor->mailSentTooManyTimes($email));
  }

  /**
   * Tests that five identical emails are considered excessive.
   */
  public function testMailSentTooManyTimesReturnsTrueAtLimit(): void {
    $email = $this->createEmail();

    $this->createLogItems(5);

    $this->assertTrue($this->mailMonitor->mailSentTooManyTimes($email));
  }

  /**
   * Tests that older identical emails outside the time window are ignored.
   */
  public function testMailSentTooManyTimesIgnoresOldLogItems(): void {
    $email = $this->createEmail();

    $this->createLogItems(5, [
      'created' => \Drupal::time()->getRequestTime() - (121 * 60),
    ]);

    $this->assertFalse($this->mailMonitor->mailSentTooManyTimes($email));
  }

  /**
   * Tests that emails with different subjects are ignored.
   */
  public function testMailSentTooManyTimesIgnoresDifferentSubject(): void {
    $email = $this->createEmail();

    $this->createLogItems(5, [
      'subject' => 'Different subject',
    ]);

    $this->assertFalse($this->mailMonitor->mailSentTooManyTimes($email));
  }

  /**
   * Tests that emails with different text bodies are ignored.
   */
  public function testMailSentTooManyTimesIgnoresDifferentTextBody(): void {
    $email = $this->createEmail();

    $this->createLogItems(5, [
      'text_body' => 'Different body',
    ]);

    $this->assertFalse($this->mailMonitor->mailSentTooManyTimes($email));
  }

  /**
   * Tests that emails with different types are ignored.
   */
  public function testMailSentTooManyTimesIgnoresDifferentType(): void {
    $email = $this->createEmail();

    $this->createLogItems(5, [
      'type' => 'different_type',
    ]);

    $this->assertFalse($this->mailMonitor->mailSentTooManyTimes($email));
  }

  /**
   * Tests that emails with different subtypes are ignored.
   */
  public function testMailSentTooManyTimesIgnoresDifferentSubType(): void {
    $email = $this->createEmail();

    $this->createLogItems(5, [
      'sub_type' => 'different_sub_type',
    ]);

    $this->assertFalse($this->mailMonitor->mailSentTooManyTimes($email));
  }

  /**
   * Tests that emails without a subtype match log items without a subtype.
   */
  public function testMailSentTooManyTimesHandlesMissingSubType(): void {
    $email = $this->createEmail([
      'sub_type' => NULL,
    ]);

    $this->createLogItems(5, [
      'sub_type' => NULL,
    ]);

    $this->assertTrue($this->mailMonitor->mailSentTooManyTimes($email));
  }

  /**
   * Tests that emails without a subtype do not match log items with a subtype.
   */
  public function testMailSentTooManyTimesWithMissingSubTypeIgnoresSubTypedLogs(): void {
    $email = $this->createEmail([
      'sub_type' => NULL,
    ]);

    $this->createLogItems(5, [
      'sub_type' => 'test_sub_type',
    ]);

    $this->assertFalse($this->mailMonitor->mailSentTooManyTimes($email));
  }

  /**
   * Tests that any matching recipient is enough to count a log item.
   */
  public function testMailSentTooManyTimesMatchesAnyRecipient(): void {
    $email = $this->createEmail([
      'recipients' => [
        'first-recipient@example.com',
        'second-recipient@example.com',
      ],
    ]);

    $this->createLogItems(5, [
      'to' => ['second-recipient@example.com'],
    ]);

    $this->assertTrue($this->mailMonitor->mailSentTooManyTimes($email));
  }

  /**
   * Tests that non-matching recipients are ignored.
   */
  public function testMailSentTooManyTimesIgnoresDifferentRecipient(): void {
    $email = $this->createEmail();

    $this->createLogItems(5, [
      'to' => ['different-recipient@example.com'],
    ]);

    $this->assertFalse($this->mailMonitor->mailSentTooManyTimes($email));
  }

  /**
   * Creates Symfony mailer log entities.
   *
   * @param int $count
   *   Number of log entities to create.
   * @param array $values
   *   Field values to override default log entity values.
   */
  private function createLogItems(int $count, array $values = []): void {
    $values += [
      'type' => 'test_type',
      'sub_type' => 'test_sub_type',
      'subject' => 'Test subject',
      'text_body' => 'Test body',
      'to' => ['recipient@example.com'],
      'created' => \Drupal::time()->getRequestTime(),
    ];

    for ($i = 0; $i < $count; $i++) {
      $entity = $this->logStorage->create(array_filter($values, static function ($value): bool {
        return $value !== NULL;
      }));
      $entity->save();
    }
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