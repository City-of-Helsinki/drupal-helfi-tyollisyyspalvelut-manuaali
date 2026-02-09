<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general;

/**
 * Helper functions used to temporarily prevent sending mail.
 */
class PreventMailUtility {

  /**
   * State API block mail key for all mails.
   */
  private const ALL_MAIL_KEY = 'hel_tpm_general.block_mail';

  /**
   * State API block mail key for ready to publish services.
   */
  private const READY_TO_PUBLISH_SERVICES_KEY = 'hel_tpm_general.block_mail.ready_to_publish_services';

  /**
   * State API block mail key for ready to publish services.
   */
  private const PUBLISHED_SERVICES_KEY = 'hel_tpm_general.block_mail.published_services';

  /**
   * State API block mail key for update reminders for services.
   */
  private const UPDATE_REMINDER_SERVICES_KEY = 'hel_tpm_general.block_mail.update_reminder_services';

  /**
   * State API block mail key for update reminders for outdated services.
   */
  private const UPDATE_REMINDER_OUTDATED_SERVICES_KEY = 'hel_tpm_general.block_mail.update_reminder_outdated';

  /**
   * State API block mail key for services missing updaters.
   */
  private const SERVICES_MISSING_UPDATERS_KEY = 'hel_tpm_general.block_mail.services_missing_updaters';

  /**
   * State API block mail key for user expiration.
   */
  private const USER_EXPIRATION_KEY = 'hel_tpm_general.block_mail.user_expiration';

  /**
   * State API block mail key for deactivating former group member account.
   */
  private const GROUP_ACCOUNT_BLOCKED_KEY = 'hel_tpm_general.block_mail.group_account_blocked';

  /**
   * Get the block mail state for all compatible mails.
   *
   * @return bool
   *   TRUE if sending mail is be blocked, FALSE otherwise.
   */
  public static function isBlocked(): bool {
    if (\Drupal::state()->get(self::ALL_MAIL_KEY) === TRUE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the block mail state for ready to publish services.
   *
   * @return bool
   *   TRUE if sending mail is be blocked, FALSE otherwise.
   */
  public static function isReadyToPublishServicesBlocked(): bool {
    if (\Drupal::state()->get(self::READY_TO_PUBLISH_SERVICES_KEY) === TRUE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the block mail state for published services.
   *
   * @return bool
   *   TRUE if sending mail is be blocked, FALSE otherwise.
   */
  public static function isPublishedServicesBlocked(): bool {
    if (\Drupal::state()->get(self::PUBLISHED_SERVICES_KEY) === TRUE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the block mail state for service update reminder.
   *
   * @return bool
   *   TRUE if sending mail is be blocked, FALSE otherwise.
   */
  public static function isUpdateReminderBlocked(): bool {
    if (\Drupal::state()->get(self::UPDATE_REMINDER_SERVICES_KEY) === TRUE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the block mail state for outdated services.
   *
   * @return bool
   *   TRUE if sending mail is be blocked, FALSE otherwise.
   */
  public static function isUpdateReminderOutdatedBlocked(): bool {
    if (\Drupal::state()->get(self::UPDATE_REMINDER_OUTDATED_SERVICES_KEY) === TRUE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the block mail state for services missing updaters.
   *
   * @return bool
   *   TRUE if sending mail is be blocked, FALSE otherwise.
   */
  public static function isServiceMissingUpdatersBlocked(): bool {
    if (\Drupal::state()->get(self::SERVICES_MISSING_UPDATERS_KEY) === TRUE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the block mail state for user expiration.
   *
   * @return bool
   *   TRUE if sending mail is be blocked, FALSE otherwise.
   */
  public static function isUserExpirationBlocked(): bool {
    if (\Drupal::state()->get(self::USER_EXPIRATION_KEY) === TRUE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the block mail state for deactivating group membership account.
   *
   * @return bool
   *   TRUE if sending mail is be blocked, FALSE otherwise.
   */
  public static function isDeactivatedGroupAccountBlocked(): bool {
    if (\Drupal::state()->get(self::GROUP_ACCOUNT_BLOCKED_KEY) === TRUE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Set the block mail state for all compatible mails.
   *
   * @param bool $block
   *   TRUE if sending mail should be blocked, FALSE otherwise.
   *
   * @return void
   *   Void.
   */
  public static function block(bool $block = TRUE): void {
    \Drupal::state()->set(self::ALL_MAIL_KEY, $block);
  }

  /**
   * Set the block mail state for ready to publish services.
   *
   * @param bool $block
   *   TRUE if sending mail should be blocked, FALSE otherwise.
   *
   * @return void
   *   Void.
   */
  public static function blockReadyToPublishServices(bool $block = TRUE): void {
    \Drupal::state()->set(self::READY_TO_PUBLISH_SERVICES_KEY, $block);
  }

  /**
   * Set the block mail state for published services.
   *
   * @param bool $block
   *   TRUE if sending mail should be blocked, FALSE otherwise.
   *
   * @return void
   *   Void.
   */
  public static function blockPublishedServices(bool $block = TRUE): void {
    \Drupal::state()->set(self::PUBLISHED_SERVICES_KEY, $block);
  }

  /**
   * Set the block mail state for service update reminders.
   *
   * @param bool $block
   *   TRUE if sending mail should be blocked, FALSE otherwise.
   *
   * @return void
   *   Void.
   */
  public static function blockUpdateReminder(bool $block = TRUE): void {
    \Drupal::state()->set(self::UPDATE_REMINDER_SERVICES_KEY, $block);
  }

  /**
   * Set the block mail state for outdated services.
   *
   * @param bool $block
   *   TRUE if sending mail should be blocked, FALSE otherwise.
   *
   * @return void
   *   Void.
   */
  public static function blockServiceOutdated(bool $block = TRUE): void {
    \Drupal::state()->set(self::UPDATE_REMINDER_OUTDATED_SERVICES_KEY, $block);
  }

  /**
   * Set the block mail state for services missing updaters.
   *
   * @param bool $block
   *   TRUE if sending mail should be blocked, FALSE otherwise.
   *
   * @return void
   *   Void.
   */
  public static function blockServiceMissingUpdaters(bool $block = TRUE): void {
    \Drupal::state()->set(self::SERVICES_MISSING_UPDATERS_KEY, $block);
  }

  /**
   * Set the block mail state for user expiration.
   *
   * @param bool $block
   *   TRUE if sending mail should be blocked, FALSE otherwise.
   *
   * @return void
   *   Void.
   */
  public static function blockUserExpiration(bool $block = TRUE): void {
    \Drupal::state()->set(self::USER_EXPIRATION_KEY, $block);
  }

  /**
   * Set the block mail state for deactivating group membership account.
   *
   * @param bool $block
   *   TRUE if sending mail should be blocked, FALSE otherwise.
   *
   * @return void
   *   Void.
   */
  public static function blockDeactivatedGroupAccount(bool $block = TRUE): void {
    \Drupal::state()->set(self::GROUP_ACCOUNT_BLOCKED_KEY, $block);
  }

}
