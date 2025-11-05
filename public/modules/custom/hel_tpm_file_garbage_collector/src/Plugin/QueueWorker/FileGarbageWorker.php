<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'hel_tpm_file_garbage_collector_file_garbage_worker' queue worker.
 *
 * @QueueWorker(
 *   id = "hel_tpm_file_garbage_collector_file_garbage_worker",
 *   title = @Translation("File Garbage Worker"),
 *   cron = {"time" = 60},
 * )
 */
final class FileGarbageWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new FileGarbageWorker instance.
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
    $storage = $this->entityTypeManager->getStorage('file');
    $file = $storage->load($data['fid']);
    $file->delete();
  }

}
