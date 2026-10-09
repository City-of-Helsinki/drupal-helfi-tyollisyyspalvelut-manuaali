<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector_report\Drush\Commands;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\hel_tpm_file_garbage_collector_report\FileGarbageReport;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lists files deleted, or selected for deletion in dry run mode.
 */
#[AsCommand(
  name: self::NAME,
  description: 'List files deleted, or selected for deletion in dry run mode, by the file garbage collector.',
  aliases: ['fgc-report'],
)]
#[CLI\FieldLabels(labels: [
  'fid' => 'File ID',
  'status' => 'Status',
  'filename' => 'File name',
  'uri' => 'URI',
  'filemime' => 'MIME type',
  'filesize' => 'Size (bytes)',
  'file_created' => 'Uploaded',
  'file_owner' => 'Owner ID',
  'usages' => 'Used in',
  'last_used' => 'Last used',
  'references_removed' => 'Revision values removed',
  'created' => 'First reported',
  'changed' => 'Reported',
])]
#[CLI\DefaultTableFields(fields: ['fid', 'status', 'filename', 'usages', 'last_used', 'changed'])]
#[CLI\FilterDefaultField(field: 'filename')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class FileGarbageReportCommand extends Command {

  use AutowireTrait;
  use FormatterTrait;

  /**
   * The command name.
   */
  public const NAME = 'hel-tpm-file-gc:report';

  /**
   * Constructs a FileGarbageReportCommand object.
   */
  public function __construct(
    protected readonly FormatterManager $formatterManager,
    private readonly FileGarbageReport $report,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addOption(
        'status',
        NULL,
        InputOption::VALUE_REQUIRED,
        'Only list entries with this status: dry_run or deleted.',
        NULL,
        [FileGarbageReport::STATUS_DRY_RUN, FileGarbageReport::STATUS_DELETED],
      )
      ->addOption('limit', NULL, InputOption::VALUE_REQUIRED, 'Maximum number of entries, 0 for all.', 50)
      ->addUsage('--status=dry_run')
      ->addUsage('--limit=0 --filter="fid=123"')
      ->addUsage('--status=deleted --limit=0 --format=csv > deleted-files.csv');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $status = $input->getOption('status') ?: NULL;
    if ($status !== NULL && !in_array($status, [FileGarbageReport::STATUS_DRY_RUN, FileGarbageReport::STATUS_DELETED], TRUE)) {
      $output->writeln(sprintf('<error>Invalid status "%s", use dry_run or deleted.</error>', $status));
      return Command::INVALID;
    }

    $this->writeFormattedOutput($input, $output, $this->doExecute($status, (int) $input->getOption('limit')));
    return Command::SUCCESS;
  }

  /**
   * Builds the report rows.
   *
   * @param string|null $status
   *   Only list entries with this status.
   * @param int $limit
   *   Maximum number of entries, 0 for all.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The report rows.
   */
  public function doExecute(?string $status, int $limit): RowsOfFields {
    $rows = [];
    foreach ($this->report->getEntries($status, $limit) as $entry) {
      $entry['usages'] = implode('; ', array_map($this->formatUsage(...), $entry['usages']));
      foreach (['file_created', 'last_used', 'created', 'changed'] as $key) {
        $entry[$key] = $entry[$key] ? date('Y-m-d H:i:s', (int) $entry[$key]) : '';
      }
      $rows[$entry['fid']] = $entry;
    }
    return new RowsOfFields($rows);
  }

  /**
   * Formats a usage as plain text.
   *
   * @param array $usage
   *   The usage from the report entry.
   *
   * @return string
   *   E.g. 'node 12 "Service" rev 34, 35 via ...'.
   */
  private function formatUsage(array $usage): string {
    $text = sprintf('%s %s', $usage['entity_type'], $usage['entity_id']);
    if (!empty($usage['label'])) {
      $text .= sprintf(' "%s"', $usage['label']);
    }
    if (!empty($usage['revision_ids'])) {
      $text .= ' rev ' . implode(', ', $usage['revision_ids']);
    }
    if (!empty($usage['host'])) {
      $text .= ' via ' . $this->formatUsage($usage['host']);
    }
    return $text;
  }

}
