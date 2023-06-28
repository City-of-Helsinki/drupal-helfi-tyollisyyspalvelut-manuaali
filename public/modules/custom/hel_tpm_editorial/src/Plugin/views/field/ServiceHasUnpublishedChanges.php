<?php

namespace Drupal\hel_tpm_editorial\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Provides Service has unpublished changes field handler.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("hel_tpm_editorial_service_has_unpublished_changes")
 *
 * @DCG
 * The plugin needs to be assigned to a specific table column through
 * hook_views_data() or hook_views_data_alter().
 * For non-existent columns (i.e. computed fields) you need to override
 * self::query() method.
 */
class ServiceHasUnpublishedChanges extends FieldPluginBase {

  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity;
    if (!$entity instanceof NodeInterface) {
      return [];
    }
    if ($entity->bundle() !== 'service') {
      return [];
    }

    if (!$entity->isLatestRevision()) {
      return $this->linkGenerator()->generate('Unpublished changes', $this->latestRevisionUrl($entity));
    }

    return ['#markup' => $this->t('Up to date')];
  }

  /**
   * Generate latest revision url.
   *
   * @param \Drupal\node\NodeInterface $entity
   *
   * @return \Drupal\Core\Url
   */
  protected function latestRevisionUrl(NodeInterface $entity) {
    $url = sprintf('/node/%s/latest', $entity->id());
    return Url::fromUserInput($url);
  }
}
