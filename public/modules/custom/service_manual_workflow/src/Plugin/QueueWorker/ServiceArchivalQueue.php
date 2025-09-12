<?php

declare(strict_types=1);

namespace Drupal\service_manual_workflow\Plugin\QueueWorker;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
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

  use StringTranslationTrait;

  /**
   * Constructs a new ServiceArchivalService instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Time $time,
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
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $node = $this->entityTypeManager->getStorage('node')->loadRevision($data['vid']);
    $languages = $node->getTranslationLanguages();
    foreach ($languages as $language) {
      $translation = $node->getTranslation($language->getId());
      $this->setServiceArchived($translation);
    }
  }

  /**
   * Sets the given service node to the archived state.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity to be archived.
   *
   * @return void
   *   This method does not return any value.
   */
  protected function setServiceArchived(NodeInterface $node) {
    if ($node->isDefaultTranslation()) {
      $node->setRevisionUserId(1);
      $node->setChangedTime($this->time->getRequestTime());
      $node->setRevisionLogMessage($this->t('Set automatically to archived'));
      $node->setRevisionCreationTime($this->time->getRequestTime());
    }
    $node->setRevisionTranslationAffected(TRUE);
    $node->set('moderation_state', 'archived');
    $node->save();
  }

}
