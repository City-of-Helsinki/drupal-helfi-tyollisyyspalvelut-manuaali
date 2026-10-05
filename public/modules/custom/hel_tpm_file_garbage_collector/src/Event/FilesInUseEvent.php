<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event for files the garbage collector found to be in use.
 */
final class FilesInUseEvent extends Event {

  /**
   * Constructs a FilesInUseEvent object.
   *
   * @param int[] $fids
   *   The IDs of the files in use.
   */
  public function __construct(
    public readonly array $fids,
  ) {}

}
