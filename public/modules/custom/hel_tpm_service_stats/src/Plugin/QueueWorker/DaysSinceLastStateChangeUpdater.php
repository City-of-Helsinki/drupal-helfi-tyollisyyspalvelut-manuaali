<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_stats\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
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
   * Class constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\TimeInterface $datetimeTime
   *   The datetime time service.
   * @param \Drupal\hel_tpm\Service\RevisionHistoryService $helTpmServiceStatsRevisionHistory
   *   The revision history service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel interface.
   *
   * @return void
   *   Void.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $datetimeTime,
    private readonly RevisionHistoryService $helTpmServiceStatsRevisionHistory,
    private readonly Connection $connection,
    private readonly LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->storage = $this->entityTypeManager->getStorage('node');
    $this->database = $this->connection;
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
      $container->get('database'),
      $container->get('logger.factory')->get('hel_tpm_service_stats')
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
      if (empty($revisions)) {
        $this->logger->warning('No revisions found for ' . $nid);
        continue;
      }
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
    if (empty($node)) {
      $this->logger->warning('Node @node was not found while calculating days since last state change.', ['@node' => $nid]);
      return [];
    }
    $languages = $node->getTranslationLanguages();
    foreach ($languages as $language) {
      $vid = $this->storage->getLatestTranslationAffectedRevisionId($node->id(), $language->getId());
      $revision = $this->storage->loadRevision($vid);
      if (empty($revision)) {
        $this->logger->warning('Missing revision for node: @node revision id: @vid langcode @langcode ', [
          '@node' => $nid,
          '@langcode' => $language->getId(),
          '@vid' => $vid,
        ]);
        continue;

      }
      $translation_revision = $revision->getTranslation($language->getId());
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
    $this->database->update('node__field_days_since_last_state_chan')
      ->fields(['field_days_since_last_state_chan_value' => $this->helTpmServiceStatsRevisionHistory->getTimeSinceLastStateChange($node)])
      ->condition('revision_id', $node->getRevisionId())
      ->condition('langcode', $node->language()->getId())
      ->execute();
  }

}
