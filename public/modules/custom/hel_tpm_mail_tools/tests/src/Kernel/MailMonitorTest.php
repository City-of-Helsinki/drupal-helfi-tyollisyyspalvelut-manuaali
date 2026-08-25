<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_mail_tools\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the mail monitor service.
 *
 * @group hel_tpm_mail_tools
 */
final class MailMonitorTest extends KernelTestBase {

  use CreateEmailTrait;

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
   * Tests that empty subjects and whitespace-only bodies are treated as equal.
   */
  public function testMailSentTooManyTimesMatchesEmptySubjectAndWhitespaceOnlyTextBody(): void {
    $email = $this->createEmail([
      'subject' => NULL,
      'text_body' => "\n",
    ]);

    $this->createLogItems(5, [
      'subject' => '',
      'text_body' => "  \n ",
    ]);

    $this->assertTrue($this->mailMonitor->mailSentTooManyTimes($email));
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

}
