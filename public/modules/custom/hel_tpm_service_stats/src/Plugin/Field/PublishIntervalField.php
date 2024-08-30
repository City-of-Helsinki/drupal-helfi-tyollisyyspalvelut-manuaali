<?php

namespace Drupal\hel_tpm_service_stats\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Plugin implementation of the 'total_price' field type.
 *
 * @FieldType(
 *   id = "hel_tpm_service_stats_publish_interval",
 *   label = @Translation("Publish interval"),
 *   description = @Translation("Service publish interval"),
 *   category = @Translation("Custom"),
 *   default_widget = "string_text",
 *   default_formatter = "basic_string"
 * )
 */
class PublishIntervalField extends FieldItemList implements FieldItemListInterface {
  use ComputedItemListTrait;

  protected function computeValue() {
    $entity = $this->getEntity();
    $interval = ($entity->getPublishDate() - $entity->getPreviousDate()) / 84000;
    $this->list[0] = $this->createItem(0, (int) $interval);
  }

}