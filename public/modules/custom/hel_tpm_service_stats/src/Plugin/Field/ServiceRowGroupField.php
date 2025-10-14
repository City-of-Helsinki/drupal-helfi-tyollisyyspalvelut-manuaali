<?php

namespace Drupal\hel_tpm_service_stats\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\group\Entity\GroupMembership;

/**
 * Service row group field.
 */
class ServiceRowGroupField extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $entity = $this->getEntity();
    $node_revision = $storage->loadRevision($entity->getPublishVid());
    if (empty($node_revision)) {
      return;
    }
    $group_memberships = GroupMembership::loadByEntity($node_revision);
    foreach ($group_memberships as $membership) {
      $this->list[] = $this->createItem(0, $membership->getGroup());
    }
  }

}
