<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector_report\Plugin\views\area;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\hel_tpm_file_garbage_collector_report\FileGarbageReport;
use Drupal\views\Attribute\ViewsArea;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows the file garbage collector mode and the number of reported files.
 *
 * @ingroup views_area_handlers
 */
#[ViewsArea("hel_tpm_file_garbage_collector_report_summary")]
final class FileGarbageSummary extends AreaPluginBase {

  /**
   * Constructs a FileGarbageSummary object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileGarbageReport $report,
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
      $container->get('config.factory'),
      $container->get('hel_tpm_file_garbage_collector_report.report'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE): array {
    if ($empty && empty($this->options['empty'])) {
      return [];
    }

    $modes = [
      'disabled' => $this->t('Disabled'),
      'dry_run' => $this->t('Dry run'),
      'delete' => $this->t('Delete'),
    ];
    $config = $this->configFactory->get('hel_tpm_file_garbage_collector.settings');
    $mode = $config->get('mode');

    $items = [
      $this->t('Current mode: @mode', ['@mode' => $modes[$mode] ?? $mode]),
    ];
    foreach (FileGarbageReport::getStatusLabels() as $status => $label) {
      $items[] = $this->t('@status: @count', ['@status' => $label, '@count' => $this->report->count($status)]);
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Summary'),
      '#items' => $items,
      '#cache' => [
        'tags' => [FileGarbageReport::CACHE_TAG, ...$config->getCacheTags()],
      ],
    ];
  }

}
