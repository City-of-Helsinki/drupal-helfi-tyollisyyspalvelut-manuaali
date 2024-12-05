<?php

declare(strict_types=1);

namespace Drupal\service_manual_workflow\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'service_manual_workflow_service_archival_queue' queue worker.
 *
 * @QueueWorker(
 *   id = "service_manual_workflow_service_archival_queue",
 *   title = @Translation("Service archival queue"),
 *   cron = {"time" = 60},
 * )
 */
final class ServiceArchivalQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new ServiceArchivalService instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $node = $this->entityTypeManager->getStorage('node')->loadRevision($data['vid']);
    $node->set('moderation_state', 'archived');
    if ($node->isPublished()) {
      $node->set('status', 0);
    }
    $node->setNewRevision(TRUE);
    $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $node->setRevisionUserId(1);
    $node->save();
  }

}
