<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector\Event;

/**
 * Defines events dispatched by the file garbage collector.
 */
final class FileGarbageCollectorEvents {

  /**
   * Dispatched when files are found to be in use.
   *
   * Dispatched for each collected batch and when the queue worker finds a
   * queued file in use again.
   *
   * @Event
   *
   * @see \Drupal\hel_tpm_file_garbage_collector\Event\FilesInUseEvent
   */
  public const FILES_IN_USE = 'hel_tpm_file_garbage_collector.files_in_use';

  /**
   * Dispatched when an unused file is left in place in dry run mode.
   *
   * @Event
   *
   * @see \Drupal\hel_tpm_file_garbage_collector\Event\FileGarbageEvent
   */
  public const FILE_DRY_RUN = 'hel_tpm_file_garbage_collector.file_dry_run';

  /**
   * Dispatched after an unused file has been deleted.
   *
   * Dispatched inside the transaction of the deletion: an exception thrown by
   * a subscriber rolls the deletion back.
   *
   * @Event
   *
   * @see \Drupal\hel_tpm_file_garbage_collector\Event\FileGarbageEvent
   */
  public const FILE_DELETED = 'hel_tpm_file_garbage_collector.file_deleted';

}
