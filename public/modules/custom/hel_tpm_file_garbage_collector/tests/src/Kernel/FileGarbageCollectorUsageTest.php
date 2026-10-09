<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_file_garbage_collector\Kernel;

use Consolidation\OutputFormatters\FormatterManager;
use Drupal\hel_tpm_file_garbage_collector\Drush\Commands\FileGarbageCheckCommand;
use Drupal\hel_tpm_file_garbage_collector\Drush\Commands\FileGarbageCollectCommand;
use Drupal\hel_tpm_file_garbage_collector\Event\FileGarbageCollectorEvents;
use Drupal\hel_tpm_file_garbage_collector\Event\FileGarbageEvent;
use Drupal\hel_tpm_file_garbage_collector\Event\FilesInUseEvent;
use Drupal\hel_tpm_file_garbage_collector\FileGarbageCollector;
use Drupal\hel_tpm_file_garbage_collector\Hook\FileGarbageCollectorHooks;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\hel_tpm_file_garbage_collector\Traits\FileGarbageCollectorTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tests file usage detection of the file garbage collector.
 *
 * Covers image fields, usages the collector cannot evaluate, per entity
 * latest revisions, files in composite entities (paragraphs), the queue
 * worker, events, cron and the Drush commands.
 */
#[Group('hel_tpm_file_garbage_collector')]
#[RunTestsInSeparateProcesses]
final class FileGarbageCollectorUsageTest extends EntityKernelTestBase {

  use FileGarbageCollectorTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hel_tpm_file_garbage_collector',
    'file',
    'image',
    'node',
    'entity_reference_revisions',
    'paragraphs',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpFileGarbageCollector();
  }

  /**
   * Tests that image fields and unknown usages are handled safely.
   */
  public function testImageFieldsAndUnknownUsages(): void {
    $image1 = $this->createImageFile('image-1.png');
    $image2 = $this->createImageFile('image-2.png');
    $editor_file = $this->createTextFile('editor.txt');
    $user_file = $this->createTextFile('user.txt');

    $node = $this->createNode(['image_test' => $image1->id()], '-8 months');
    $this->saveNewRevision($node, ['image_test' => $image2->id()], '-7 months');

    // Usage registered by another module than file fields, e.g. editor.
    $this->container->get('file.usage')->add($editor_file, 'editor', 'node', $node->id());
    // Usage on an entity type that is not revisionable.
    $this->container->get('file.usage')->add($user_file, 'file', 'user', $this->createUser()->id());

    $this->garbageCollector->collect();

    // Only the image removed in an old revision can be deleted.
    $this->assertSame([(int) $image1->id()], $this->getQueuedFids());
  }

  /**
   * Tests that latest revisions are checked per entity.
   */
  public function testPendingRevisionOfOtherEntityKeepsFile(): void {
    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');
    $file3 = $this->createTextFile('file3.txt');

    $node = $this->createNode(['file_test' => [$file1->id()]], '-12 months');

    // Old pending draft which adds a new file.
    $storage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\node\NodeInterface $draft */
    $draft = $storage->createRevision($node, FALSE);
    $draft->set('file_test', [$file1->id(), $file2->id()]);
    $this->setTimestamps($draft, '-8 months');
    $draft->save();

    // Another node is edited after the draft was created.
    $this->createNode(['file_test' => [$file3->id()]], 'now');

    $this->garbageCollector->collect();

    // File 2 is in the latest revision of the first node.
    $this->assertSame([], $this->getQueuedFids());
  }

  /**
   * Tests files referenced from paragraphs.
   */
  public function testParagraphFiles(): void {
    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');
    $file3 = $this->createTextFile('file3.txt');

    $paragraph = Paragraph::create([
      'type' => 'file_paragraph',
      'paragraph_file' => [$file1->id()],
    ]);
    $node = $this->createNode(['paragraphs_test' => [$paragraph]], '-8 months');

    $node = $this->replaceParagraphFile($node, $file2, '-7 months');
    $this->garbageCollector->collect();
    // File 1 was removed from the paragraph more than 6 months ago.
    $this->assertSame([(int) $file1->id()], $this->getQueuedFids());

    $this->replaceParagraphFile($node, $file3, '-1 month');
    $this->garbageCollector->collect();
    // File 2 was removed from the paragraph within the time limit.
    $this->assertSame([(int) $file1->id()], $this->getQueuedFids());
  }

  /**
   * Tests that the time limit is counted from the removal of the file.
   */
  public function testRecentlyRemovedFileIsKept(): void {
    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');

    // The file was added long ago but removed only recently.
    $node = $this->createNode(['file_test' => [$file1->id()]], '-12 months');
    $node = $this->saveNewRevision($node, ['file_test' => [$file2->id()]], '-1 month');
    $this->garbageCollector->collect();
    $this->assertSame([], $this->getQueuedFids());

    // Once the removal is older than the time limit the file can be deleted.
    $this->setRevisionTimestamps($node, '-7 months');
    $this->garbageCollector->collect();
    $this->assertSame([(int) $file1->id()], $this->getQueuedFids());
  }

  /**
   * Tests that the queue worker re-checks usage before deleting.
   */
  public function testWorker(): void {
    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');
    $node = $this->createNode(['file_test' => [$file1->id(), $file2->id()]], '-8 months');
    $old_revision_id = $node->getRevisionId();
    $node = $this->saveNewRevision($node, ['file_test' => [$file2->id()]], '-7 months');

    $file_storage = $this->entityTypeManager->getStorage('file');
    $node_storage = $this->entityTypeManager->getStorage('node');
    $worker = $this->getWorker();

    // Nothing is deleted when disabled or in dry run mode (the default).
    foreach (['disabled', 'dry_run'] as $mode) {
      $this->config('hel_tpm_file_garbage_collector.settings')->set('mode', $mode)->save();
      $worker->processItem(['fid' => $file1->id()]);
      $file_storage->resetCache();
      $this->assertNotNull($file_storage->load($file1->id()), $mode);
    }

    $this->config('hel_tpm_file_garbage_collector.settings')->set('mode', 'delete')->save();
    $worker->processItem(['fid' => $file1->id()]);
    // File 2 is used by the default revision and must be kept.
    $worker->processItem(['fid' => $file2->id()]);

    $file_storage->resetCache();
    $this->assertNull($file_storage->load($file1->id()));
    $this->assertNotNull($file_storage->load($file2->id()));

    // The deleted file is removed from the old revision only.
    $node_storage->resetCache();
    $old_revision = $node_storage->loadRevision($old_revision_id);
    $this->assertSame([['target_id' => (int) $file2->id()]], $this->getTargetIds($old_revision));
    $this->assertSame([['target_id' => (int) $file2->id()]], $this->getTargetIds($node_storage->load($node->id())));
  }

  /**
   * Tests the events dispatched by the collector and the queue worker.
   */
  public function testEvents(): void {
    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');
    $node = $this->createNode(['file_test' => [$file1->id(), $file2->id()]], '-8 months');
    $this->saveNewRevision($node, ['file_test' => [$file2->id()]], '-7 months');

    $events = [];
    $event_dispatcher = $this->container->get('event_dispatcher');
    $event_names = [
      FileGarbageCollectorEvents::FILES_IN_USE,
      FileGarbageCollectorEvents::FILE_DRY_RUN,
      FileGarbageCollectorEvents::FILE_DELETED,
    ];
    foreach ($event_names as $name) {
      $event_dispatcher->addListener($name, function ($event) use ($name, &$events): void {
        $events[] = [$name, $event];
      });
    }

    // Collecting reports the files in use.
    $this->garbageCollector->collect();
    $this->assertCount(1, $events);
    [$name, $event] = array_shift($events);
    $this->assertSame(FileGarbageCollectorEvents::FILES_IN_USE, $name);
    $this->assertInstanceOf(FilesInUseEvent::class, $event);
    $this->assertSame([(int) $file2->id()], $event->fids);

    // The worker reports a queued file which is in use again.
    $worker = $this->getWorker();
    $worker->processItem(['fid' => $file2->id()]);
    [$name, $event] = array_shift($events);
    $this->assertSame(FileGarbageCollectorEvents::FILES_IN_USE, $name);
    $this->assertSame([(int) $file2->id()], $event->fids);

    // Dry run.
    $worker->processItem(['fid' => $file1->id()]);
    [$name, $event] = array_shift($events);
    $this->assertSame(FileGarbageCollectorEvents::FILE_DRY_RUN, $name);
    $this->assertInstanceOf(FileGarbageEvent::class, $event);
    $this->assertEquals($file1->id(), $event->file->id());
    $this->assertFalse($event->evaluation['active']);
    $this->assertNull($event->referencesRemoved);

    // Deletion.
    $this->config('hel_tpm_file_garbage_collector.settings')->set('mode', 'delete')->save();
    $worker->processItem(['fid' => $file1->id()]);
    [$name, $event] = array_shift($events);
    $this->assertSame(FileGarbageCollectorEvents::FILE_DELETED, $name);
    $this->assertSame('public://file1.txt', $event->file->getFileUri());
    $this->assertSame(1, $event->referencesRemoved);
    $this->assertSame([], $events);
  }

  /**
   * Tests that a failing FILE_DELETED subscriber rolls the deletion back.
   */
  public function testDeletedEventExceptionRollsBack(): void {
    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');
    $node = $this->createNode(['file_test' => [$file1->id()]], '-8 months');
    $this->saveNewRevision($node, ['file_test' => [$file2->id()]], '-7 months');

    $this->container->get('event_dispatcher')->addListener(FileGarbageCollectorEvents::FILE_DELETED, function (): void {
      throw new \RuntimeException('Report is not available.');
    });
    $this->config('hel_tpm_file_garbage_collector.settings')->set('mode', 'delete')->save();

    try {
      $this->getWorker()->processItem(['fid' => $file1->id()]);
      $this->fail('The exception was not thrown.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame('Report is not available.', $e->getMessage());
    }

    $file_storage = $this->entityTypeManager->getStorage('file');
    $file_storage->resetCache();
    $this->assertNotNull($file_storage->load($file1->id()));
    $this->assertNotEmpty($this->container->get('file.usage')->listUsage($file_storage->load($file1->id())));
  }

  /**
   * Tests that cron only collects files when the collector is enabled.
   */
  public function testCronRespectsMode(): void {
    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');
    $node = $this->createNode(['file_test' => [$file1->id()]], '-8 months');
    $this->saveNewRevision($node, ['file_test' => [$file2->id()]], '-7 months');
    $module_handler = $this->container->get('module_handler');

    $this->config('hel_tpm_file_garbage_collector.settings')->set('mode', 'disabled')->save();
    $module_handler->invoke('hel_tpm_file_garbage_collector', 'cron');
    $this->assertSame([], $this->getQueuedFids());

    $this->config('hel_tpm_file_garbage_collector.settings')->set('mode', 'dry_run')->save();
    $module_handler->invoke('hel_tpm_file_garbage_collector', 'cron');
    $this->assertSame([(int) $file1->id()], $this->getQueuedFids());

    // Collection runs at most once a day.
    $module_handler->invoke('hel_tpm_file_garbage_collector', 'cron');
    $this->assertSame([], $this->getQueuedFids());
  }

  /**
   * Tests batched collection and resuming a collection cycle on cron.
   */
  public function testBatchedCollection(): void {
    $files = [];
    foreach (range(1, 4) as $i) {
      $files[$i] = $this->createTextFile("file$i.txt");
    }
    $fid = static fn (int $i): int => (int) $files[$i]->id();

    // Files 1-3 were removed more than 6 months ago, file 4 is in use.
    $node = $this->createNode(['file_test' => [$fid(1), $fid(2), $fid(3)]], '-8 months');
    $this->saveNewRevision($node, ['file_test' => [$fid(4)]], '-7 months');

    $this->assertSame($fid(2), $this->garbageCollector->collectBatch(0, 2));
    $this->assertSame([$fid(1), $fid(2)], $this->getQueuedFids());
    $this->assertSame($fid(4), $this->garbageCollector->collectBatch($fid(2), 2));
    $this->assertSame([$fid(3)], $this->getQueuedFids());
    $this->assertNull($this->garbageCollector->collectBatch($fid(4), 2));

    // Cron continues an unfinished cycle from the stored cursor.
    $state = $this->container->get('state');
    $state->set(FileGarbageCollectorHooks::CURSOR_STATE, $fid(2));
    $this->container->get('module_handler')->invoke('hel_tpm_file_garbage_collector', 'cron');
    $this->assertSame([$fid(3)], $this->getQueuedFids());
    $this->assertNull($state->get(FileGarbageCollectorHooks::CURSOR_STATE));
    $this->assertNotNull($state->get(FileGarbageCollectorHooks::LAST_RUN_STATE));
  }

  /**
   * Tests that a new cycle waits until the previous queue is processed.
   */
  public function testCronWaitsForQueue(): void {
    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');
    $node = $this->createNode(['file_test' => [$file1->id()]], '-8 months');
    $this->saveNewRevision($node, ['file_test' => [$file2->id()]], '-7 months');

    $this->queue->createItem(['fid' => 999]);
    $this->container->get('module_handler')->invoke('hel_tpm_file_garbage_collector', 'cron');
    $this->assertSame([999], $this->getQueuedFids());
  }

  /**
   * Tests the reasons given by the evaluation.
   */
  public function testEvaluationReasons(): void {
    $files = [];
    foreach (['old', 'recent', 'current', 'mixed', 'editor', 'user', 'unreferenced', 'none'] as $name) {
      $files[$name] = $this->createTextFile("$name.txt");
    }
    $fid = static fn (string $name): int => (int) $files[$name]->id();

    $node = $this->createNode(['file_test' => [$fid('old'), $fid('recent'), $fid('current'), $fid('mixed')]], '-12 months');
    $node = $this->saveNewRevision($node, ['file_test' => [$fid('recent'), $fid('current')]], '-7 months');
    $node = $this->saveNewRevision($node, ['file_test' => [$fid('current')]], '-1 month');

    $file_usage = $this->container->get('file.usage');
    $file_usage->add($files['mixed'], 'editor', 'node', $node->id());
    $file_usage->add($files['editor'], 'editor', 'node', $node->id());
    $file_usage->add($files['user'], 'file', 'user', $this->createUser()->id());
    $file_usage->add($files['unreferenced'], 'file', 'node', $node->id());

    $expected = [
      'old' => FileGarbageCollector::REASON_UNUSED,
      'recent' => FileGarbageCollector::REASON_RECENTLY_USED,
      'current' => FileGarbageCollector::REASON_CURRENT_REVISION,
      'mixed' => FileGarbageCollector::REASON_OTHER_MODULE,
      'editor' => FileGarbageCollector::REASON_OTHER_MODULE,
      'user' => FileGarbageCollector::REASON_UNSUPPORTED,
      'unreferenced' => FileGarbageCollector::REASON_NOT_REFERENCED,
      'none' => FileGarbageCollector::REASON_NO_USAGE,
    ];
    foreach ($expected as $name => $reason) {
      $this->assertSame($reason, $this->garbageCollector->evaluateFile($fid($name))['reason'], $name);
    }

    // With $all, every usage is evaluated even after an active one.
    $evaluation = $this->garbageCollector->evaluateFile($fid('mixed'), TRUE);
    $this->assertTrue($evaluation['active']);
    $reasons = array_column($evaluation['usages'], 'reason', 'module');
    ksort($reasons);
    $this->assertSame([
      'editor' => FileGarbageCollector::REASON_OTHER_MODULE,
      'file' => FileGarbageCollector::REASON_UNUSED,
    ], $reasons);
  }

  /**
   * Tests the check command output.
   */
  public function testCheckCommand(): void {
    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');
    $node = $this->createNode(['file_test' => [$file1->id()]], '-8 months');
    $old_revision_id = $node->getRevisionId();
    $node = $this->saveNewRevision($node, ['file_test' => [$file2->id()]], '-7 months');

    $command = new FileGarbageCheckCommand(
      new FormatterManager(),
      $this->garbageCollector,
      $this->entityTypeManager,
    );

    $evaluation = $this->garbageCollector->evaluateFile((int) $file1->id(), TRUE);
    $this->assertStringContainsString('can be deleted', $command->getSummary((int) $file1->id(), $file1->getFileUri(), $evaluation)[0]);
    $row = $command->doExecute($evaluation)->getArrayCopy()[0];
    $this->assertSame('file', $row['module']);
    $this->assertSame('node ' . $node->id(), $row['entity']);
    $this->assertSame($node->label(), $row['label']);
    $this->assertSame((string) $old_revision_id, $row['revisions']);
    $this->assertSame('Unused', $row['verdict']);
    $this->assertSame(date('Y-m-d H:i:s', (int) $node->getChangedTime()), $row['last_used']);

    $evaluation = $this->garbageCollector->evaluateFile((int) $file2->id(), TRUE);
    $this->assertStringContainsString('is kept', $command->getSummary((int) $file2->id(), $file2->getFileUri(), $evaluation)[0]);
    $this->assertSame('Kept', $command->doExecute($evaluation)->getArrayCopy()[0]['verdict']);
  }

  /**
   * Tests the collect command.
   */
  public function testCollectCommand(): void {
    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');
    $node = $this->createNode(['file_test' => [$file1->id()]], '-8 months');
    $this->saveNewRevision($node, ['file_test' => [$file2->id()]], '-7 months');

    $state = $this->container->get('state');
    $command = new FileGarbageCollectCommand(
      $this->garbageCollector,
      $this->entityTypeManager,
      $state,
      $this->container->get('datetime.time'),
      $this->container->get('config.factory'),
      $this->container->get('plugin.manager.queue_worker'),
    );
    $output = new BufferedOutput();
    $io = new SymfonyStyle(new ArrayInput([]), $output);

    // Collect only: dry run mode is the default.
    $state->set(FileGarbageCollectorHooks::CURSOR_STATE, 1);
    $this->assertSame(Command::SUCCESS, $command->doExecute(FALSE, $io));
    $this->assertSame(1, $this->queue->numberOfItems());
    $this->assertStringContainsString('1 queued for deletion', $output->fetch());
    $this->assertNull($state->get(FileGarbageCollectorHooks::CURSOR_STATE));
    $this->assertNotNull($state->get(FileGarbageCollectorHooks::LAST_RUN_STATE));

    // Collecting again would queue the files twice.
    $this->assertSame(Command::FAILURE, $command->doExecute(FALSE, $io));
    $this->assertSame(1, $this->queue->numberOfItems());

    // Processing in dry run mode keeps the file.
    $output->fetch();
    $this->assertSame(Command::SUCCESS, $command->doExecute(TRUE, $io));
    $this->assertStringContainsString('0 deleted.', $output->fetch());
    $this->assertSame(0, $this->queue->numberOfItems());
    $file_storage = $this->entityTypeManager->getStorage('file');
    $file_storage->resetCache();
    $this->assertNotNull($file_storage->load($file1->id()));

    // Processing in delete mode deletes the file.
    $this->config('hel_tpm_file_garbage_collector.settings')->set('mode', 'delete')->save();
    $this->assertSame(Command::SUCCESS, $command->doExecute(TRUE, $io));
    $this->assertStringContainsString('Processed 1 queued files, 1 deleted.', $output->fetch());
    $file_storage->resetCache();
    $this->assertNull($file_storage->load($file1->id()));
    $this->assertNotNull($file_storage->load($file2->id()));

    // Processing is refused when the collector is disabled.
    $this->config('hel_tpm_file_garbage_collector.settings')->set('mode', 'disabled')->save();
    $this->assertSame(Command::FAILURE, $command->doExecute(TRUE, $io));
  }

  /**
   * Tests that deleting a file still in use is refused.
   */
  public function testDeleteFileRefusesActiveFile(): void {
    $file = $this->createTextFile('file1.txt');
    $this->createNode(['file_test' => [$file->id()]], '-12 months');

    $this->expectException(\LogicException::class);
    $this->garbageCollector->deleteFile($file);
  }

}
