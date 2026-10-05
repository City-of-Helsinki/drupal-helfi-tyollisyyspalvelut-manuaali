<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;
use Drupal\hel_tpm_file_garbage_collector\FileGarbageCollector;

/**
 * Hook implementations for File garbage collector module.
 */
final class FileGarbageCollectorHooks {

  /**
   * State key storing when the last collection cycle was completed.
   */
  public const LAST_RUN_STATE = 'hel_tpm_file_garbage_collector_collect_last_run';

  /**
   * State key storing the file ID a running collection cycle continues from.
   */
  public const CURSOR_STATE = 'hel_tpm_file_garbage_collector_collect_cursor';

  /**
   * Minimum interval between collection cycles in seconds.
   */
  private const INTERVAL = 86400;

  /**
   * Maximum time in seconds spent on collection per cron run.
   */
  private const TIME_BUDGET = 30;

  /**
   * Constructs a FileGarbageCollectorHooks object.
   */
  public function __construct(
    private readonly StateInterface $state,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileGarbageCollector $garbageCollector,
  ) {}

  /**
   * Implements hook_cron().
   *
   * Evaluates used files in batches within a time budget and continues on
   * the next cron run. A new cycle starts at most once a day, and only after
   * the files queued by the previous cycle have been processed.
   */
  #[Hook('cron')]
  public function cron(): void {
    $mode = $this->configFactory->get('hel_tpm_file_garbage_collector.settings')->get('mode');
    if ($mode === 'disabled') {
      return;
    }

    $cursor = $this->state->get(self::CURSOR_STATE);
    if ($cursor === NULL) {
      $last_run = (int) $this->state->get(self::LAST_RUN_STATE, 0);
      if ($last_run > $this->time->getCurrentTime() - self::INTERVAL) {
        return;
      }
      if ($this->garbageCollector->getQueue()->numberOfItems() > 0) {
        return;
      }
      $cursor = 0;
    }

    $deadline = $this->time->getCurrentMicroTime() + self::TIME_BUDGET;
    do {
      $cursor = $this->garbageCollector->collectBatch((int) $cursor, FileGarbageCollector::BATCH_SIZE);
      if ($cursor === NULL) {
        $this->state->delete(self::CURSOR_STATE);
        $this->state->set(self::LAST_RUN_STATE, $this->time->getCurrentTime());
        return;
      }
    } while ($this->time->getCurrentMicroTime() < $deadline);

    $this->state->set(self::CURSOR_STATE, $cursor);
  }

}
