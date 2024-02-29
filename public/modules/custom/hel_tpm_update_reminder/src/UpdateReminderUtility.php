<?php

declare(strict_types = 1);

namespace Drupal\hel_tpm_update_reminder;

/**
 * Helper functions for update reminders.
 */
class UpdateReminderUtility {

  /**
   * Update reminder cron run limit in hours.
   */
  public const RUN_LIMIT_HOURS = 24;

  /**
   * First limit in days for service reminders.
   */
  public const SERVICE_LIMIT_1 = 120;

  /**
   * Second limit in days for service reminders.
   */
  public const SERVICE_LIMIT_2 = 135;

  /**
   * Third limit in days for service reminders.
   */
  public const SERVICE_LIMIT_3 = 150;

  /**
   * State API key for update reminder last run timestamp.
   */
  private const LAST_RUN_KEY = 'hel_tpm_update_reminder.last_run';

  /**
   * Base State API key for node's last checked timestamp.
   */
  private const CHECKED_TIMESTAMP_BASE_KEY = 'hel_tpm_update_reminder.checked_timestamp.node.';

  /**
   * Base State API key for node's messages sent counter.
   */
  private const MESSAGES_SENT_BASE_KEY = 'hel_tpm_update_reminder.messages_sent.node.';

  /**
   * Get the last run timestamp.
   *
   * @return int|null
   *   The timestamp for last run.
   */
  public static function getLastRun(): ?int {
    return \Drupal::state()->get(self::LAST_RUN_KEY, NULL);
  }

  /**
   * Updates the last run timestamp to current time.
   *
   * @return void
   *   Void.
   */
  public static function updateLastRun(): void {
    \Drupal::state()->set(self::LAST_RUN_KEY, \Drupal::time()->getRequestTime());
  }

  /**
   * Check whether the update reminder should be run according to last run.
   *
   * @return bool
   *   TRUE if update reminder should be run, FALSE otherwise.
   */
  public static function shouldRun(): bool {
    if (empty($lastRun = self::getLastRun())) {
      return TRUE;
    }
    $runTimeLimit = strtotime(self::RUN_LIMIT_HOURS . ' hours', 0);
    if ((\Drupal::time()->getRequestTime() - $lastRun) < $runTimeLimit) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Get the checked content timestamp for node.
   *
   * @param int $nid
   *   The node id.
   *
   * @return int|null
   *   The checked content timestamp if existing, NULL otherwise.
   */
  public static function getChecked(int $nid): ?int {
    return \Drupal::state()->get(self::CHECKED_TIMESTAMP_BASE_KEY . $nid, NULL);
  }

  /**
   * Set node content as checked with current timestamp.
   *
   * @param int $nid
   *   The node id.
   *
   * @return void
   *   Void.
   */
  public static function setChecked(int $nid): void {
    \Drupal::state()->set(self::CHECKED_TIMESTAMP_BASE_KEY . $nid, \Drupal::time()->getRequestTime());
  }

  /**
   * Get the sent messages state for node.
   *
   * @param int $nid
   *   The node id.
   *
   * @return int
   *   The sent messages.
   */
  public static function getMessagesSent(int $nid): int {
    return \Drupal::state()->get(self::MESSAGES_SENT_BASE_KEY . $nid, 0);
  }

  /**
   * Set the sent messages state for node.
   *
   * @param int $nid
   *   The node id.
   * @param int $messageNumber
   *   The updated sent messages.
   *
   * @return void
   *   Void.
   */
  public static function setMessagesSent(int $nid, int $messageNumber): void {
    \Drupal::state()->set(self::MESSAGES_SENT_BASE_KEY . $nid, $messageNumber);
  }

  /**
   * Marks node content as checked.
   *
   * @param int $nid
   *   The node id.
   *
   * @return void
   *   Void.
   */
  public static function markAsChecked(int $nid): void {
    if (!empty(\Drupal::state()->get(self::MESSAGES_SENT_BASE_KEY . $nid))) {
      self::setMessagesSent($nid, 0);
    }
    self::setChecked($nid);
  }

  /**
   * Get the first limit for service reminders as timestamp.
   *
   * @return int|false
   *   A timestamp on success, FALSE otherwise.
   */
  public static function getFirstServiceLimit(): int|false {
    return strtotime('-' . self::SERVICE_LIMIT_1 . ' days');
  }

  /**
   * Get the second limit for service reminders as timestamp.
   *
   * @return int|false
   *   A timestamp on success, FALSE otherwise.
   */
  public static function getSecondServiceLimit(): int|false {
    return strtotime('-' . self::SERVICE_LIMIT_2 . ' days');
  }

  /**
   * Get the third limit for service reminders as timestamp.
   *
   * @return int|false
   *   A timestamp on success, FALSE otherwise.
   */
  public static function getThirdServiceLimit(): int|false {
    return strtotime('-' . self::SERVICE_LIMIT_3 . ' days');
  }

}
