<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_mail_tools\Utility;

/**
 * Helper functions to temporarily prevent sending mail and creating messages.
 */
class PreventMailUtility {

  /**
   * Block mail State API key.
   */
  private const BLOCK_MAIL_KEY = 'hel_tpm_mail_tools.block_mail';

  /**
   * Block message key prefix for State API.
   */
  private const BLOCK_MESSAGE_PREFIX = 'hel_tpm_mail_tools.block_message.';

  /**
   * Block message flag for ready to publish services.
   */
  public const SERVICES_READY_TO_PUBLISH = 'services_ready_to_publish';

  /**
   * Block message flag for published services.
   */
  public const SERVICES_PUBLISHED = 'services_published';

  /**
   * Block message flag for update reminders for services.
   */
  public const SERVICES_UPDATE_REMINDER = 'services_update_reminder';

  /**
   * Block message flag for update reminders for outdated services.
   */
  public const SERVICES_OUTDATED_REMINDER = 'services_outdated_reminder';

  /**
   * Block message flag for services missing updaters.
   */
  public const SERVICES_MISSING_UPDATERS = 'services_missing_updaters';

  /**
   * Block message flag for user expiration.
   */
  public const USER_EXPIRATION = 'user_expiration';

  /**
   * Block message flag for deactivating former group member account.
   */
  public const GROUP_ACCOUNT_BLOCKED = 'group_account_blocked';

  /**
   * Get the block mail state for all compatible mails.
   *
   * @return bool
   *   TRUE if sending mail is be blocked, FALSE otherwise.
   */
  public static function isMailBlocked(): bool {
    if (\Drupal::state()->get(self::BLOCK_MAIL_KEY) === TRUE) {
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
  public static function blockMail(bool $block = TRUE): void {
    \Drupal::state()->set(self::BLOCK_MAIL_KEY, $block);
  }

  /**
   * Get message templates for all message flags.
   *
   * @return array
   *   Message flags as keys, array of message templates as values.
   */
  public static function getAllTemplates(): array {
    return [
      self::SERVICES_READY_TO_PUBLISH => [
        'group_ready_to_publish_notificat',
      ],
      self::SERVICES_PUBLISHED => [
        'content_has_been_published',
      ],
      self::SERVICES_UPDATE_REMINDER => [
        'hel_tpm_update_reminder_service',
        'hel_tpm_update_reminder_service2',
      ],
      self::SERVICES_OUTDATED_REMINDER => [
        'hel_tpm_update_reminder_outdated',
      ],
      self::SERVICES_MISSING_UPDATERS => [
        'services_missing_updaters',
      ],
      self::USER_EXPIRATION => [
        '1st_user_account_expiry_reminder',
        '2nd_user_account_expiry_reminder',
        'hel_tpm_user_expiry_blocked',
      ],
      self::GROUP_ACCOUNT_BLOCKED => [
        'hel_tpm_group_account_blocked',
      ],
    ];
  }

  /**
   * Get message templates for given flag.
   *
   * @param string $flag
   *   Message flag.
   *
   * @return array|null
   *   Message template IDs, NULL if flag is not supported.
   */
  public static function getTemplates(string $flag): ?array {
    $templates = self::getAllTemplates();
    if (!isset($templates[$flag])) {
      return NULL;
    }
    return $templates[$flag];
  }

  /**
   * Get flag for given message template.
   *
   * @param string $template
   *   Message template ID.
   *
   * @return string|int|null
   *   First message flag matching the template, NULL if nothing matches.
   */
  public static function getFlag(string $template): string|int|null {
    foreach (self::getAllTemplates() as $flag => $templates) {
      if (in_array($template, $templates, TRUE)) {
        return $flag;
      }
    }
    return NULL;
  }

  /**
   * Get the block message state for given message flag.
   *
   * @param string $flag
   *   Message flag.
   *
   * @return bool|null
   *   TRUE if sending mail is be blocked, FALSE if not, NULL if flag is not
   *   supported.
   */
  public static function isMessageBlocked(string $flag): ?bool {
    if (!self::getTemplates($flag)) {
      return NULL;
    }

    if (\Drupal::state()->get(self::BLOCK_MESSAGE_PREFIX . $flag) === TRUE) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get block message state for given message template.
   *
   * @param string $template
   *   Message template ID.
   *
   * @return bool|null
   *   TRUE if sending message is blocked, FALSE if not, NULL if template is
   *   not supported.
   */
  public static function isTemplateBlocked(string $template): ?bool {
    return self::isMessageBlocked(self::getFlag($template));
  }

  /**
   * Set block message state for given message flag.
   *
   * @param string $flag
   *   Message flag.
   * @param bool $block
   *   TRUE if sending messages should be blocked, FALSE otherwise.
   *
   * @return void
   *   Void.
   */
  public static function blockMessage(string $flag, bool $block = TRUE): void {
    if (!self::getTemplates($flag)) {
      return;
    }

    \Drupal::state()->set(self::BLOCK_MESSAGE_PREFIX . $flag, $block);
  }

}
