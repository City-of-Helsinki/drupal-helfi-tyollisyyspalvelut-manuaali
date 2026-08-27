<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_mail_tools\Kernel;

use Drupal\symfony_mailer\EmailInterface;

/**
 * Provides a helper to create mocked Symfony Mailer emails for testing.
 */
trait CreateEmailTrait {

  /**
   * Creates a mocked Symfony Mailer email.
   *
   * @param array $values
   *   Optional email values to override defaults.
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
    $email->method('getTo')->willReturn(
      array_map(
        static fn (string $address): TestEmailAddress => new TestEmailAddress($address),
        $values['recipients'],
      )
    );

    return $email;
  }

}
