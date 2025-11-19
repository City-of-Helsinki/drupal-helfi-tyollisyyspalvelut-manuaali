<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_stats\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
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
    private readonly Connection $connection,
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
      $container->get('database')
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
      $revisions = $this->getTranslationRevisions($nid);
      foreach ($revisions as $node) {
        if ($node->bundle() != 'service') {
          continue;
        }
        $this->updateNodeLastStateChangeTimestamp($node);
      }
    }
  }

  /**
   * Retrieves the latest translation revisions for a given node ID.
   *
   * @param int $nid
   *   The node ID for which the translation revisions are to be retrieved.
   *
   * @return array
   *   An associative array where the keys are language IDs and the values are
   *   their respective latest translation revisions.
   */
  protected function getTranslationRevisions($nid) {
    $node_revisions = [];
    $node = $this->storage->load($nid);
    $languages = $node->getTranslationLanguages();
    foreach ($languages as $language) {
      $vid = $this->storage->getLatestTranslationAffectedRevisionId($node->id(), $language->getId());
      $node = $this->storage->loadRevision($vid);
      $translation_revision = $node->getTranslation($language->getId());
      $node_revisions[$language->getId()] = $translation_revision;
    }
    return $node_revisions;
  }

  /**
   * Updates the last state change timestamp of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity to update.
   *
   * @return void
   *   This method does not return a value.
   */
  public function updateNodeLastStateChangeTimestamp($node) {
    $node->set('field_days_since_last_state_chan', $this->helTpmServiceStatsRevisionHistory->getTimeSinceLastStateChange($node));
    $node->setChangedTime($node->getChangedTime() + 1);
    $node->setRevisionCreationTime($node->getRevisionCreationTime());
    $node->setRevisionTranslationAffected(TRUE);
    $node->setSyncing(TRUE);
    $node->setNewRevision(FALSE);
    $node->save();
  }

}
