<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_mail_tools\Kernel;

/**
 * A test double for email address objects.
 */
final class TestEmailAddress {

  /**
   * Constructs a test email address.
   */
  public function __construct(private readonly string $email) {
  }

  /**
   * Gets the email address.
   */
  public function getEmail(): string {
    return $this->email;
  }

}
