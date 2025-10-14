<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_stats\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\hel_tpm_service_stats\RevisionHistoryService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines Days Since Last State Change Updater queue worker.
 *
 * @QueueWorker(
 *   id = "hel_tpm_service_stats_days_since_last_state_change_updater",
 *   title = @Translation("Days Since Last State Change Updater"),
 *   cron = {"time" = 60},
 * )
 */
final class DaysSinceLastStateChangeUpdater extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The storage handler interface instance.
   */
  private EntityStorageInterface $storage;

  /**
   * Constructs a new DaysSinceLastStateChangeUpdater instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $datetimeTime,
    private readonly RevisionHistoryService $helTpmServiceStatsRevisionHistory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->storage = $this->entityTypeManager->getStorage('node');
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
      $container->get('datetime.time'),
      $container->get('hel_tpm_service_stats.revision_history'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (empty($data)) {
      return;
    }
    foreach ($data as $nid) {
      $node = $this->storage->load($nid);
      if ($node->bundle() != 'service') {
        continue;
      }
      $this->updateNodeLastStateChangeTimestamp($node);
    }
  }

  /**
   * Updates the last state change information of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity to update the last state change information for.
   *
   * @return void
   *   This method does not return anything. It modifies the node entity
   *   and persists the changes to the storage.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function updateNodeLastStateChangeTimestamp($node) {
    $time_since_last_state_change = $this->helTpmServiceStatsRevisionHistory->getTimeSinceLastStateChange($node);

    if (!$node->isDefaultRevision()) {
      return;
    }

    $changed = $node->getChangedTime();
    $revision_creation_time = $node->getRevisionCreationTime();

    $node->set('field_days_since_last_state_chan', $time_since_last_state_change);
    $node->setChangedTime($changed + 1);
    $node->setRevisionCreationTime($revision_creation_time);
    $node->setSyncing(TRUE);
    $node->setNewRevision(FALSE);

    $node->save();
  }

}
