<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_stats;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;

/**
 * Handles the execution of cron jobs related to TPM service statistics.
 *
 * This class is responsible for determining the appropriate times for cron
 * execution based on a predefined schedule, processing nodes
 * related to services, and queuing tasks for further processing.
 */
final class HelTpmServiceStatsCron {

  /**
   * An array containing scheduled times in a 24-hour format.
   *
   * @var array|string[]
   */
  private array $schedule = ['01:00', '04:00'];

  /**
   * Entity storage interface.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $storage;

  /**
   * Queue service.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  private $queue;

  /**
   * Constructs a HelTpmServiceStatsCron object.
   */
  public function __construct(
    private readonly TimeInterface $datetimeTime,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StateInterface $state,
    private readonly QueueFactory $queueFactory,
  ) {
    $this->storage = $this->entityTypeManager->getStorage('node');
    $this->queue = $this->queueFactory->get('hel_tpm_service_stats_days_since_last_state_change_updater');
  }

  /**
   * Processes cron tasks for managing service statistics.
   *
   * The method checks whether the scheduled task should run and processes
   * the items if necessary. If the $force parameter is true, it bypasses
   * the regular scheduling check, and performs the cron operation forcibly.
   *
   * @param bool $force
   *   A flag to indicate if the cron job should run forcibly, regardless of
   *   the scheduled time. Defaults to false.
   *
   * @return void
   *   This method does not return a value.
   */
  public function cron($force = FALSE): void {
    if (!$this->shouldRun() && $force === FALSE) {
      return;
    }
    $request_time = $this->datetimeTime->getRequestTime();
    $nids = $this->storage->getQuery()
      ->condition('type', 'service')
      ->condition('changed', $request_time - 86400, "<")
      ->addTag('exclude_archived_services')
      ->accessCheck(FALSE)
      ->execute();
    if (empty($nids)) {
      return;
    }

    $chunks = array_chunk($nids, 10);
    foreach ($chunks as $chunk) {
      $this->queue->createItem($chunk);
    }
    $this->state->set('hel_tpm_service_stats.last_cron', $this->datetimeTime->getRequestTime());
  }

  /**
   * Determines if a scheduled task should run now based on time and last run.
   *
   * The method evaluates the next scheduled execution time of the task by
   * comparing it to the current time. If the current time has passed the next
   * scheduled execution time, the method will return true indicating that the
   * task should run.
   *
   * @return bool
   *   True if the task should run, false otherwise.
   */
  public function shouldRun() {
    $now = $this->datetimeTime->getRequestTime();

    $timezone = new \DateTimeZone(date_default_timezone_get());

    $current_time = DrupalDateTime::createFromFormat('U', $now)
      ->setTimezone($timezone)
      ->format('H');

    $first_limit = explode(':', $this->schedule[0]);
    $last_limit = explode(':', $this->schedule[1]);
    if ($current_time < $first_limit[0] || $current_time > $last_limit[0]) {
      return FALSE;
    }

    $timestamp_last = $this->state->get('hel_tpm_service_stats.last_cron') ?? 0;
    $last = DrupalDateTime::createFromFormat('U', $timestamp_last)
      ->setTimezone($timezone);
    $next = clone $last;

    $next->setTime(...$first_limit);
    // If the cron ran on the same calendar day it should have, add one day.
    if ($next->getTimestamp() <= $last->getTimestamp()) {
      $next->modify('+1 day');
    }

    return $next->getTimestamp() <= $now;
  }

}
