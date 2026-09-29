<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\file\FileInterface;

/**
 * Event for an unused file handled by the garbage collector.
 */
final class FileGarbageEvent extends Event {

  /**
   * Constructs a FileGarbageEvent object.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file. On FILE_DELETED the entity is already deleted, but its values
   *   are still available.
   * @param array $evaluation
   *   The evaluation of the file, see
   *   \Drupal\hel_tpm_file_garbage_collector\FileGarbageCollector::evaluateFile().
   * @param int|null $referencesRemoved
   *   On FILE_DELETED, the number of old revision field values removed with
   *   the file, otherwise NULL.
   */
  public function __construct(
    public readonly FileInterface $file,
    public readonly array $evaluation,
    public readonly ?int $referencesRemoved = NULL,
  ) {}

}
