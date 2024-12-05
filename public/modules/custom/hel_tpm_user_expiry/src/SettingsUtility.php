<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_user_expiry;

/**
 * Helper functions for storing user expiry settings.
 */
class SettingsUtility {

  /**
   * User expiration status key for the State API.
   */
  private const USER_EXPIRATION_ENABLED = 'hel_tpm_user_expiry.user_expiration_enabled';

  /**
   * Get user expiration status.
   *
   * @return bool
   *   TRUE if user expiration is enabled, FALSE otherwise.
   */
  public static function getUserExpirationStatus(): bool {
    if (\Drupal::state()->get(self::USER_EXPIRATION_ENABLED, TRUE) === TRUE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Enable user expiration.
   *
   * @return void
   *   Void.
   */
  public static function enableUserExpiration(): void {
    \Drupal::state()->set(self::USER_EXPIRATION_ENABLED, TRUE);
  }

  /**
   * Disable user expiration.
   *
   * @return void
   *   Void.
   */
  public static function disableUserExpiration(): void {
    \Drupal::state()->set(self::USER_EXPIRATION_ENABLED, FALSE);
  }

}
