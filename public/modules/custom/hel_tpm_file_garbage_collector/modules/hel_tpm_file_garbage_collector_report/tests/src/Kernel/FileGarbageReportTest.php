<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_file_garbage_collector_report\Kernel;

use Consolidation\OutputFormatters\FormatterManager;
use Drupal\hel_tpm_file_garbage_collector_report\Drush\Commands\FileGarbageReportCommand;
use Drupal\hel_tpm_file_garbage_collector_report\FileGarbageReport;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\hel_tpm_file_garbage_collector\Traits\FileGarbageCollectorTestTrait;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the report of files deleted or selected for deletion.
 */
#[Group('hel_tpm_file_garbage_collector')]
#[RunTestsInSeparateProcesses]
final class FileGarbageReportTest extends EntityKernelTestBase {

  use FileGarbageCollectorTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hel_tpm_file_garbage_collector',
    'hel_tpm_file_garbage_collector_report',
    'file',
    'image',
    'node',
    'entity_reference_revisions',
    'paragraphs',
    'views',
  ];

  /**
   * The report service.
   *
   * @var \Drupal\hel_tpm_file_garbage_collector_report\FileGarbageReport
   */
  private FileGarbageReport $report;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpFileGarbageCollector();
    $this->installSchema('hel_tpm_file_garbage_collector_report', [FileGarbageReport::TABLE]);
    $this->report = $this->container->get('hel_tpm_file_garbage_collector_report.report');
  }

  /**
   * Tests the report entries of dry run and deleted files.
   */
  public function testReport(): void {
    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');
    $node = $this->createNode(['file_test' => [$file1->id(), $file2->id()]], '-8 months');
    $old_revision_id = (int) $node->getRevisionId();
    $node = $this->saveNewRevision($node, ['file_test' => [$file2->id()]], '-7 months');
    $worker = $this->getWorker();

    // Dry run lists the file without deleting it. File 2 is in use.
    $worker->processItem(['fid' => $file1->id()]);
    $worker->processItem(['fid' => $file2->id()]);
    $entries = $this->report->getEntries();
    $this->assertCount(1, $entries);
    $entry = $entries[0];
    $this->assertSame(FileGarbageReport::STATUS_DRY_RUN, $entry['status']);
    $this->assertEquals($file1->id(), $entry['fid']);
    $this->assertSame('file1.txt', $entry['filename']);
    $this->assertSame('public://file1.txt', $entry['uri']);
    $this->assertSame('text/plain', $entry['filemime']);
    $this->assertEquals($file1->getOwnerId(), $entry['file_owner']);
    $this->assertEquals($node->getChangedTime(), $entry['last_used']);
    $this->assertNull($entry['references_removed']);
    $this->assertCount(1, $entry['usages']);
    $usage = $entry['usages'][0];
    $this->assertSame('node', $usage['entity_type']);
    $this->assertEquals($node->id(), $usage['entity_id']);
    $this->assertSame('article', $usage['bundle']);
    $this->assertSame($node->label(), $usage['label']);
    $this->assertSame([$old_revision_id], $usage['revision_ids']);
    $this->assertNull($usage['host']);

    // The same entry is updated when the file is deleted.
    $this->config('hel_tpm_file_garbage_collector.settings')->set('mode', 'delete')->save();
    $worker->processItem(['fid' => $file1->id()]);
    $this->assertSame(0, $this->report->count(FileGarbageReport::STATUS_DRY_RUN));
    $this->assertSame(1, $this->report->count(FileGarbageReport::STATUS_DELETED));
    $entry = $this->report->getEntry((int) $file1->id());
    $this->assertSame(FileGarbageReport::STATUS_DELETED, $entry['status']);
    $this->assertEquals(1, $entry['references_removed']);
    $this->assertSame('public://file1.txt', $entry['uri']);
  }

  /**
   * Tests that dry run entries are removed when files are used again.
   */
  public function testReportRemovesCandidatesInUseAgain(): void {
    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');
    $node = $this->createNode(['file_test' => [$file1->id()]], '-8 months');
    $node = $this->saveNewRevision($node, ['file_test' => [$file2->id()]], '-7 months');

    $this->getWorker()->processItem(['fid' => $file1->id()]);
    $this->assertSame(1, $this->report->count(FileGarbageReport::STATUS_DRY_RUN));

    // The file is added back to the content.
    $this->saveNewRevision($node, ['file_test' => [$file1->id(), $file2->id()]], 'now');
    $this->garbageCollector->collect();
    $this->assertSame([], $this->getQueuedFids());
    $this->assertSame(0, $this->report->count());
  }

  /**
   * Tests that the report shows the host of files in paragraphs.
   */
  public function testReportParagraphHost(): void {
    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');
    $paragraph = Paragraph::create([
      'type' => 'file_paragraph',
      'paragraph_file' => [$file1->id()],
    ]);
    $node = $this->createNode(['paragraphs_test' => [$paragraph]], '-8 months');
    $old_revision_id = (int) $node->getRevisionId();
    $node = $this->replaceParagraphFile($node, $file2, '-7 months');

    $this->getWorker()->processItem(['fid' => $file1->id()]);

    $entry = $this->report->getEntry((int) $file1->id());
    $usage = $entry['usages'][0];
    $this->assertSame('paragraph', $usage['entity_type']);
    $this->assertSame('node', $usage['host']['entity_type']);
    $this->assertEquals($node->id(), $usage['host']['entity_id']);
    $this->assertSame($node->label(), $usage['host']['label']);
    $this->assertSame([$old_revision_id], $usage['host']['revision_ids']);
    $this->assertEquals($node->getChangedTime(), $entry['last_used']);
  }

  /**
   * Tests the report view.
   */
  public function testReportView(): void {
    $this->installConfig(['views']);
    $this->container->get('config.installer')->installOptionalConfig(NULL, ['module' => 'hel_tpm_file_garbage_collector_report']);
    $this->container->get('router.builder')->rebuild();

    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');
    // File 1 is used by two old revisions.
    $node = $this->createNode(['file_test' => [$file1->id()]], '-9 months');
    $old_revision_id = $node->getRevisionId();
    $node = $this->saveNewRevision($node, ['title' => $this->randomMachineName()], '-8 months');
    $second_revision_id = $node->getRevisionId();
    $this->saveNewRevision($node, ['file_test' => [$file2->id()]], '-7 months');
    $this->getWorker()->processItem(['fid' => $file1->id()]);

    $route = $this->container->get('router.route_provider')->getRouteByName('view.file_garbage_collector_report.page_1');
    $this->assertSame('/admin/reports/file-garbage-collector', $route->getPath());

    // Make sure user 1, who bypasses access checks, is not used below.
    $this->createUser();
    $view = Views::getView('file_garbage_collector_report');
    $this->assertFalse($view->access('page_1', $this->createUser(['access content'])));
    $this->assertTrue($view->access('page_1', $this->createUser(['view file garbage collector report'])));

    $build = $view->preview('page_1');
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('file1.txt', $html);
    $this->assertStringContainsString('public://file1.txt', $html);
    $this->assertStringContainsString($node->label(), $html);
    $this->assertStringContainsString('Would be deleted (dry run)', $html);
    $this->assertStringContainsString('Current mode: Dry run', $html);
    // The usage is one sentence: the linked label, the entity and the linked
    // revisions separated by commas.
    $this->assertStringContainsString('>' . $node->label() . '</a> (Content ' . $node->id() . ', revisions: <a ', $html);
    $this->assertStringContainsString('>' . $old_revision_id . '</a>, <a ', $html);
    $this->assertStringContainsString('>' . $second_revision_id . '</a>)', $html);
    foreach ([$old_revision_id, $second_revision_id] as $revision_id) {
      $this->assertStringContainsString('href="/node/' . $node->id() . '/revisions/' . $revision_id . '/view"', $html);
      $this->assertStringContainsString('aria-label="Revision ' . $revision_id . ' of ' . $node->label() . '"', $html);
    }

    // The exposed status filter.
    foreach ([FileGarbageReport::STATUS_DRY_RUN => 1, FileGarbageReport::STATUS_DELETED => 0] as $status => $count) {
      $view = Views::getView('file_garbage_collector_report');
      $view->setDisplay('page_1');
      $view->setExposedInput(['status' => $status]);
      $view->execute();
      $this->assertCount($count, $view->result, $status);
    }
  }

  /**
   * Tests the report command rows.
   */
  public function testReportCommand(): void {
    $file1 = $this->createTextFile('file1.txt');
    $file2 = $this->createTextFile('file2.txt');
    $node = $this->createNode(['file_test' => [$file1->id()]], '-8 months');
    $old_revision_id = $node->getRevisionId();
    $this->saveNewRevision($node, ['file_test' => [$file2->id()]], '-7 months');
    $this->getWorker()->processItem(['fid' => $file1->id()]);

    $command = new FileGarbageReportCommand(new FormatterManager(), $this->report);
    $rows = $command->doExecute(FileGarbageReport::STATUS_DRY_RUN, 0)->getArrayCopy();
    $this->assertCount(1, $rows);
    $row = reset($rows);
    $this->assertSame('file1.txt', $row['filename']);
    $this->assertSame(sprintf('node %s "%s" rev %s', $node->id(), $node->label(), $old_revision_id), $row['usages']);
    $this->assertSame([], $command->doExecute(FileGarbageReport::STATUS_DELETED, 0)->getArrayCopy());
  }

}
