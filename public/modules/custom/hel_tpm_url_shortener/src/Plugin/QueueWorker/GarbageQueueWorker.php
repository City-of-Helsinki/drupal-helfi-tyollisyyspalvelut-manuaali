<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_url_shortener\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'hel_tpm_url_shortener_garbage_queue_worker' queue worker.
 *
 * @QueueWorker(
 *   id = "hel_tpm_url_shortener_garbage_queue_worker",
 *   title = @Translation("Garbage Queue Worker"),
 *   cron = {"time" = 60},
 * )
 */
final class GarbageQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new GarbageQueueWorker instance.
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
    $storage = $this->entityTypeManager->getStorage('shortenerredirect');
    $entities = $storage->loadMultiple(array_keys($data));
    $storage->delete($entities);
  }

}
