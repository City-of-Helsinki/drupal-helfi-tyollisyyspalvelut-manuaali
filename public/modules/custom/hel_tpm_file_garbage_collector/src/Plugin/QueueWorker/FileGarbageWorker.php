<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hel_tpm_file_garbage_collector\Event\FileGarbageCollectorEvents;
use Drupal\hel_tpm_file_garbage_collector\Event\FileGarbageEvent;
use Drupal\hel_tpm_file_garbage_collector\Event\FilesInUseEvent;
use Drupal\hel_tpm_file_garbage_collector\FileGarbageCollector;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Deletes unused files queued by the file garbage collector.
 *
 * Respects the 'mode' setting: in 'dry_run' mode files are only logged and
 * in 'disabled' mode queued items are discarded.
 *
 * @see \Drupal\hel_tpm_file_garbage_collector\Event\FileGarbageCollectorEvents
 */
#[QueueWorker(
  id: 'hel_tpm_file_garbage_collector_file_garbage_worker',
  title: new TranslatableMarkup('File Garbage Worker'),
  cron: ['time' => 60],
)]
final class FileGarbageWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new FileGarbageWorker instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileGarbageCollector $garbageCollector,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly Connection $connection,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('hel_tpm_file_garbage_collector.collector'),
      $container->get('config.factory'),
      $container->get('logger.channel.hel_tpm_file_garbage_collector'),
      $container->get('event_dispatcher'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $mode = $this->configFactory->get('hel_tpm_file_garbage_collector.settings')->get('mode');
    if ($mode === 'disabled') {
      return;
    }

    /** @var \Drupal\file\FileInterface|null $file */
    $file = $this->entityTypeManager->getStorage('file')->load($data['fid']);
    if (empty($file)) {
      return;
    }
    $fid = (int) $file->id();

    // Usage may have changed since the item was queued.
    $evaluation = $this->garbageCollector->evaluateFile($fid);
    if ($evaluation['active']) {
      $this->eventDispatcher->dispatch(new FilesInUseEvent([$fid]), FileGarbageCollectorEvents::FILES_IN_USE);
      return;
    }

    $context = [
      '@fid' => $fid,
      '@uri' => $file->getFileUri(),
    ];
    if ($mode !== 'delete') {
      $this->eventDispatcher->dispatch(new FileGarbageEvent($file, $evaluation), FileGarbageCollectorEvents::FILE_DRY_RUN);
      $this->logger->info('Dry run: would delete unused file @fid (@uri).', $context);
      return;
    }

    // Subscribers run inside the transaction, so that e.g. a report entry is
    // written together with the deletion or not at all.
    $transaction = $this->connection->startTransaction();
    try {
      $context['@count'] = $this->garbageCollector->deleteFile($file);
      $this->eventDispatcher->dispatch(new FileGarbageEvent($file, $evaluation, $context['@count']), FileGarbageCollectorEvents::FILE_DELETED);
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
    $this->logger->info('Deleted unused file @fid (@uri) and removed @count references from old revisions.', $context);
  }

}
