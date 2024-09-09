<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_stats\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\hel_tpm_service_stats\RevisionHistoryService;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Time since last state change field handler.
 *
 * @ViewsField("hel_tpm_service_stats_time_since_last_state_change")
 *
 * @DCG
 * The plugin needs to be assigned to a specific table column through
 * hook_views_data() or hook_views_data_alter().
 * Put the following code to hel_tpm_service_stats.views.inc file.
 * @code
 * function foo_views_data_alter(array &$data): void {
 *   $data['node']['foo_example']['field'] = [
 *     'title' => t('Example'),
 *     'help' => t('Custom example field.'),
 *     'id' => 'foo_example',
 *   ];
 * }
 * @endcode
 */
final class TimeSinceLastStateChange extends FieldPluginBase {

  /**
   * Constructs a new TimeSinceLastStateChange instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly RevisionHistoryService $revision_history_service,
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
      $container->get('hel_tpm_service_stats.revision_history'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {}

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array {
    $entity = $values->_entity;
    return [
      '#markup' => $this->t('@days days', [
        '@days' => $this->revision_history_service->getTimeSinceLastStateChange($entity)
      ])
    ];
  }
}
