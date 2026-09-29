<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector\Drush\Commands;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hel_tpm_file_garbage_collector\FileGarbageCollector;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Explains whether the garbage collector keeps or deletes a file.
 */
#[AsCommand(
  name: self::NAME,
  description: 'Show whether the file garbage collector keeps or deletes a file, and why.',
  aliases: ['fgc-check'],
)]
#[CLI\FieldLabels(labels: [
  'module' => 'Module',
  'entity' => 'Entity',
  'label' => 'Label',
  'revisions' => 'Revisions',
  'verdict' => 'Verdict',
  'reason' => 'Reason',
  'last_used' => 'Last used',
  'host' => 'Host',
])]
#[CLI\DefaultTableFields(fields: ['module', 'entity', 'label', 'revisions', 'verdict', 'reason', 'last_used', 'host'])]
#[CLI\FilterDefaultField(field: 'entity')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class FileGarbageCheckCommand extends Command {

  use AutowireTrait;
  use FormatterTrait;

  /**
   * The command name.
   */
  public const NAME = 'hel-tpm-file-gc:check';

  /**
   * Descriptions of the evaluation reasons.
   */
  private const REASONS = [
    FileGarbageCollector::REASON_NO_USAGE => 'The file has no usage, core removes temporary files.',
    FileGarbageCollector::REASON_OTHER_MODULE => 'Usage registered by another module than file fields.',
    FileGarbageCollector::REASON_UNSUPPORTED => 'The entity type is not revisionable or cannot be evaluated.',
    FileGarbageCollector::REASON_NOT_REFERENCED => 'No file or image field of the entity has the file.',
    FileGarbageCollector::REASON_CURRENT_REVISION => 'Used in the default or latest revision.',
    FileGarbageCollector::REASON_RECENTLY_USED => 'Removed from the content less than the retention period ago.',
    FileGarbageCollector::REASON_UNRESOLVED_HOST => 'The host entity of the paragraph cannot be resolved.',
    FileGarbageCollector::REASON_UNUSED => 'Only used by old revisions, removed longer than the retention period ago.',
  ];

  /**
   * Constructs a FileGarbageCheckCommand object.
   */
  public function __construct(
    protected readonly FormatterManager $formatterManager,
    private readonly FileGarbageCollector $collector,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setHelp('Evaluates every usage of the file with the same rules as cron. Nothing is queued or deleted.')
      ->addArgument('fid', InputArgument::REQUIRED, 'The file ID.')
      ->addUsage('123');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $fid = (int) $input->getArgument('fid');
    // Write the summary to stderr so that --format=json|csv stays parsable.
    $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

    /** @var \Drupal\file\FileInterface|null $file */
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!$file) {
      $io->error(sprintf('File %d does not exist.', $fid));
      return Command::FAILURE;
    }

    $evaluation = $this->collector->evaluateFile($fid, TRUE);
    $io->text($this->getSummary($fid, $file->getFileUri(), $evaluation));
    if ($evaluation['usages']) {
      $this->writeFormattedOutput($input, $output, $this->doExecute($evaluation));
    }
    return Command::SUCCESS;
  }

  /**
   * Builds the summary of the evaluation.
   *
   * @param int $fid
   *   The file ID.
   * @param string $uri
   *   The file URI.
   * @param array $evaluation
   *   The evaluation, see FileGarbageCollector::evaluateFile().
   *
   * @return string[]
   *   Lines of text.
   */
  public function getSummary(int $fid, string $uri, array $evaluation): array {
    $lines = [];
    $lines[] = sprintf('File %d (%s) %s: %s', $fid, $uri,
      $evaluation['active'] ? 'is kept' : 'can be deleted',
      self::REASONS[$evaluation['reason']] ?? $evaluation['reason'],
    );
    return $lines;
  }

  /**
   * Builds a row for each usage of the file.
   *
   * @param array $evaluation
   *   The evaluation, see FileGarbageCollector::evaluateFile().
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The usage rows.
   */
  public function doExecute(array $evaluation): RowsOfFields {
    $rows = [];
    foreach ($evaluation['usages'] as $usage) {
      $rows[] = [
        'module' => $usage['module'],
        'entity' => $usage['entity_type'] . ' ' . $usage['entity_id'],
        'label' => $this->getLabel($usage['entity_type'], $usage['entity_id']),
        'revisions' => implode(', ', $usage['revision_ids']),
        'verdict' => $usage['active'] ? 'Kept' : 'Unused',
        'reason' => self::REASONS[$usage['reason']] ?? $usage['reason'],
        'last_used' => $usage['last_used'] ? date('Y-m-d H:i:s', (int) $usage['last_used']) : '',
        'host' => $this->formatHost($usage['host']),
      ];
    }
    return new RowsOfFields($rows);
  }

  /**
   * Formats the host chain of a composite entity usage.
   *
   * @param array|null $host
   *   The host, see FileGarbageCollector::evaluateUsage().
   *
   * @return string
   *   E.g. 'node 12 "Service" rev 34'.
   */
  private function formatHost(?array $host): string {
    if (!$host) {
      return '';
    }
    $text = sprintf('%s %s', $host['entity_type'], $host['entity_id']);
    if ($label = $this->getLabel($host['entity_type'], $host['entity_id'])) {
      $text .= sprintf(' "%s"', $label);
    }
    if ($host['revision_ids']) {
      $text .= ' rev ' . implode(', ', $host['revision_ids']);
    }
    if ($host['host']) {
      $text .= ' via ' . $this->formatHost($host['host']);
    }
    return $text;
  }

  /**
   * Gets the label of an entity.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int|string $entity_id
   *   The entity ID.
   *
   * @return string
   *   The label, or an empty string.
   */
  private function getLabel(string $entity_type_id, int|string $entity_id): string {
    if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
      return '';
    }
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    return $entity ? (string) $entity->label() : '';
  }

}
