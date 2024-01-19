<?php

declare(strict_types = 1);

namespace Drupal\hel_tpm_general;

/**
 * Helper functions used to temporarily prevent sending mail.
 */
class PreventMailUtility {

  /**
   * Block mail key at the State API.
   */
  private const PREVENT_MAIL_KEY = 'hel_tpm_general.block_mail';

  /**
   * Get the preventing mail state.
   *
   * @return bool
   *   TRUE if sending mail should be blocked, FALSE otherwise.
   */
  public static function get(): bool {
    if (\Drupal::state()->get(self::PREVENT_MAIL_KEY) === TRUE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Set the preventing mail state.
   *
   * @param bool $block
   *   TRUE if sending mail should be blocked, FALSE otherwise.
   *
   * @return void
   *   Void.
   */
  public static function set(bool $block = TRUE): void {
    \Drupal::state()->set(self::PREVENT_MAIL_KEY, $block);
  }

}
