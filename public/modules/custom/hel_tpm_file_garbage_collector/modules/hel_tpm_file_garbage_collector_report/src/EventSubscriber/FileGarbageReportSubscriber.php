<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector_report\EventSubscriber;

use Drupal\hel_tpm_file_garbage_collector\Event\FileGarbageCollectorEvents;
use Drupal\hel_tpm_file_garbage_collector\Event\FileGarbageEvent;
use Drupal\hel_tpm_file_garbage_collector\Event\FilesInUseEvent;
use Drupal\hel_tpm_file_garbage_collector_report\FileGarbageReport;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Records the decisions of the file garbage collector in the report.
 */
final class FileGarbageReportSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a FileGarbageReportSubscriber object.
   */
  public function __construct(
    private readonly FileGarbageReport $report,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      FileGarbageCollectorEvents::FILES_IN_USE => 'onFilesInUse',
      FileGarbageCollectorEvents::FILE_DRY_RUN => 'onFileDryRun',
      FileGarbageCollectorEvents::FILE_DELETED => 'onFileDeleted',
    ];
  }

  /**
   * Removes files taken back into use from the deletion candidates.
   *
   * @param \Drupal\hel_tpm_file_garbage_collector\Event\FilesInUseEvent $event
   *   The event.
   */
  public function onFilesInUse(FilesInUseEvent $event): void {
    $this->report->removeCandidates($event->fids);
  }

  /**
   * Lists a file that would be deleted.
   *
   * @param \Drupal\hel_tpm_file_garbage_collector\Event\FileGarbageEvent $event
   *   The event.
   */
  public function onFileDryRun(FileGarbageEvent $event): void {
    $this->report->record($event->file, $event->evaluation, FileGarbageReport::STATUS_DRY_RUN);
  }

  /**
   * Records a deleted file.
   *
   * Runs inside the transaction of the deletion, so a failure to record the
   * file also rolls back the deletion.
   *
   * @param \Drupal\hel_tpm_file_garbage_collector\Event\FileGarbageEvent $event
   *   The event.
   */
  public function onFileDeleted(FileGarbageEvent $event): void {
    $this->report->record($event->file, $event->evaluation, FileGarbageReport::STATUS_DELETED, $event->referencesRemoved);
  }

}
