<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector\Drush\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hel_tpm_file_garbage_collector\FileGarbageCollector;
use Drupal\hel_tpm_file_garbage_collector\Hook\FileGarbageCollectorHooks;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Runs the file garbage collector now.
 */
#[AsCommand(
  name: self::NAME,
  description: 'Evaluate all used files now and queue unused files, optionally processing the queue.',
  aliases: ['fgc-collect'],
)]
final class FileGarbageCollectCommand extends Command {

  use AutowireTrait;

  /**
   * The command name.
   */
  public const NAME = 'hel-tpm-file-gc:collect';

  /**
   * Constructs a FileGarbageCollectCommand object.
   */
  public function __construct(
    private readonly FileGarbageCollector $collector,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StateInterface $state,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'plugin.manager.queue_worker')]
    private readonly QueueWorkerManagerInterface $queueWorkerManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setHelp('Runs a complete collection cycle immediately instead of waiting for cron. With --process the queued files are handled right away according to the configured mode: only logged (and listed in the report, when the report module is enabled) in dry_run mode, or deleted in delete mode.')
      ->addOption('process', NULL, InputOption::VALUE_NONE, 'Process the queue after collecting.')
      ->addUsage('--process');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    return $this->doExecute((bool) $input->getOption('process'), new SymfonyStyle($input, $output));
  }

  /**
   * Collects files and optionally processes the queue.
   *
   * @param bool $process
   *   Whether to process the queue after collecting.
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The console output.
   *
   * @return int
   *   The command exit code.
   */
  public function doExecute(bool $process, SymfonyStyle $io): int {
    $mode = $this->configFactory->get('hel_tpm_file_garbage_collector.settings')->get('mode');
    $queue = $this->collector->getQueue();

    if ($process && $mode === 'disabled') {
      $io->error('The file garbage collector is disabled, queued files would be discarded. Set the mode to dry_run or delete first.');
      return Command::FAILURE;
    }
    $pending = $queue->numberOfItems();
    if ($pending > 0 && !$process) {
      $io->error(sprintf('The queue already has %d items. Process them first with --process or "drush queue:run %s".', $pending, FileGarbageCollector::$queue));
      return Command::FAILURE;
    }

    $io->text(sprintf('Mode: %s', $mode));
    $this->collector->collect();
    // A complete cycle was run, so cron does not need to start another one.
    $this->state->delete(FileGarbageCollectorHooks::CURSOR_STATE);
    $this->state->set(FileGarbageCollectorHooks::LAST_RUN_STATE, $this->time->getCurrentTime());
    $queued = $queue->numberOfItems() - $pending;
    $io->success(sprintf('Evaluated all used files, %d queued for deletion.', $queued));

    if (!$process) {
      $io->note('Queued files are processed by cron, or run this command with --process.');
      return Command::SUCCESS;
    }

    $file_storage = $this->entityTypeManager->getStorage('file');
    $worker = $this->queueWorkerManager->createInstance(FileGarbageCollector::$queue);
    $processed = 0;
    $deleted = 0;
    $failed = 0;
    while ($item = $queue->claimItem()) {
      $fid = $item->data['fid'] ?? NULL;
      try {
        $existed = $fid && $file_storage->load($fid);
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
        $file_storage->resetCache([$fid]);
        if ($existed && !$file_storage->load($fid)) {
          $deleted++;
        }
      }
      catch (\Throwable $e) {
        // Like cron, leave the item to be retried when its lease expires.
        $failed++;
        $io->error(sprintf('File %s: %s', $fid ?? '?', $e->getMessage()));
      }
    }

    $io->success(sprintf('Processed %d queued files, %d deleted.', $processed, $deleted));
    if ($mode === 'dry_run') {
      $io->note('Dry run: no files were deleted. With the report module enabled, see the files that would be deleted with "drush fgc-report --status=dry_run".');
    }
    return $failed ? Command::FAILURE : Command::SUCCESS;
  }

}
