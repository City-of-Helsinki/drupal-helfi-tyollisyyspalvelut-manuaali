<?php

declare(strict_types=1);

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
   * First reminder limit in days.
   */
  public const LIMIT_1 = 120;

  /**
   * Second reminder limit in days, counted from the first limit.
   */
  public const LIMIT_2 = 15;

  /**
   * Third reminder limit in days, counted from the second limit.
   */
  public const LIMIT_3 = 15;

  /**
   * State API key for update reminder last run timestamp.
   */
  public const LAST_RUN_KEY = 'hel_tpm_update_reminder.last_run';

  /**
   * Base State API key for node's last checked timestamp.
   */
  public const CHECKED_TIMESTAMP_BASE_KEY = 'hel_tpm_update_reminder.checked_timestamp.node.';

  /**
   * Base State API key for node's last reminder timestamp.
   */
  public const REMINDED_BASE_KEY = 'hel_tpm_update_reminder.reminded_timestamp.node.';

  /**
   * Base State API key for node's messages sent counter.
   */
  public const MESSAGES_SENT_BASE_KEY = 'hel_tpm_update_reminder.messages_sent.node.';

  /**
   * Get the last run timestamp.
   *
   * @return int|null
   *   The timestamp for last run.
   */
  public static function getLastRunTimestamp(): ?int {
    return \Drupal::state()->get(self::LAST_RUN_KEY, NULL);
  }

  /**
   * Updates the last run timestamp to current time.
   *
   * @return void
   *   Void.
   */
  public static function updateLastRunTimestamp(): void {
    \Drupal::state()->set(self::LAST_RUN_KEY, \Drupal::time()->getRequestTime());
  }

  /**
   * Check whether the update reminder should run by checking the last run time.
   *
   * @return bool
   *   TRUE if update reminder should be run, FALSE otherwise.
   */
  public static function shouldRun(): bool {
    if (empty($lastRun = self::getLastRunTimestamp())) {
      return TRUE;
    }
    $runTimeLimit = strtotime(self::RUN_LIMIT_HOURS . ' hours', 0);
    if ((\Drupal::time()->getRequestTime() - $lastRun) < $runTimeLimit) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Get the timestamp the node is last checked.
   *
   * @param int $nid
   *   The node id.
   *
   * @return int|null
   *   The checked content timestamp if existing, NULL otherwise.
   */
  public static function getCheckedTimestamp(int $nid): ?int {
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
  public static function setCheckedTimestamp(int $nid): void {
    \Drupal::state()->set(self::CHECKED_TIMESTAMP_BASE_KEY . $nid, \Drupal::time()->getRequestTime());
  }

  /**
   * Get the reminder message timestamp for node.
   *
   * @param int $nid
   *   The node id.
   *
   * @return int|null
   *   The reminder message timestamp if existing, NULL otherwise.
   */
  public static function getRemindedTimestamp(int $nid): ?int {
    return \Drupal::state()->get(self::REMINDED_BASE_KEY . $nid, NULL);
  }

  /**
   * Set the reminder message timestamp for node to current time.
   *
   * @param int $nid
   *   The node id.
   *
   * @return void
   *   Void.
   */
  public static function setRemindedTimestamp(int $nid): void {
    \Drupal::state()->set(self::REMINDED_BASE_KEY . $nid, \Drupal::time()->getRequestTime());
  }

  /**
   * Get the sent messages number for node.
   *
   * @param int $nid
   *   The node id.
   *
   * @return int
   *   The number of sent messages.
   */
  public static function getMessagesSent(int $nid): int {
    return \Drupal::state()->get(self::MESSAGES_SENT_BASE_KEY . $nid, 0);
  }

  /**
   * Set the sent messages number for node.
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
   * Set the sent message state for node.
   *
   * The message number is updated and the reminded timestamp is set to current
   * timestamp.
   *
   * @param int $nid
   *   The node id.
   * @param int $messageNumber
   *   The updated sent messages.
   *
   * @return void
   *   Void.
   */
  public static function setMessagesSentState(int $nid, int $messageNumber): void {
    self::setMessagesSent($nid, $messageNumber);
    self::setRemindedTimestamp($nid);
  }

  /**
   * Clear the sent message state for node.
   *
   * The message number and reminded timestamp states are removed.
   *
   * @param int $nid
   *   The node id.
   *
   * @return void
   *   Void.
   */
  public static function clearMessagesSent(int $nid): void {
    if (!empty(\Drupal::state()->get(self::MESSAGES_SENT_BASE_KEY . $nid))) {
      \Drupal::state()->delete(self::MESSAGES_SENT_BASE_KEY . $nid);
    }
    if (!empty(\Drupal::state()->get(self::REMINDED_BASE_KEY . $nid))) {
      \Drupal::state()->delete(self::REMINDED_BASE_KEY . $nid);
    }
  }

  /**
   * Mark node as checked, e.g. after a state transition.
   *
   * @param int $nid
   *   The node id.
   *
   * @return void
   *   Void.
   */
  public static function checkNode(int $nid): void {
    self::clearMessagesSent($nid);
    self::setCheckedTimestamp($nid);
  }

  /**
   * Get the first limit as timestamp.
   *
   * @return int|false
   *   A timestamp on success, FALSE otherwise.
   */
  public static function getFirstLimitTimestamp(): int|false {
    return strtotime('-' . self::LIMIT_1 . ' days');
  }

  /**
   * Get the second limit as timestamp.
   *
   * @return int|false
   *   A timestamp on success, FALSE otherwise.
   */
  public static function getSecondLimitTimestamp(): int|false {
    return strtotime('-' . self::LIMIT_2 . ' days');
  }

  /**
   * Get the third limit as timestamp.
   *
   * @return int|false
   *   A timestamp on success, FALSE otherwise.
   */
  public static function getThirdLimitTimestamp(): int|false {
    return strtotime('-' . self::LIMIT_3 . ' days');
  }

}
