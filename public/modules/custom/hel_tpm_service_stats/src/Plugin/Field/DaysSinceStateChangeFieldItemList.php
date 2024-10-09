<?php

namespace Drupal\hel_tpm_service_stats\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Item list for a computed field that displays
 * how long service has been in rtb state.
 */
class DaysInRtbFieldItemList extends FieldItemList implements FieldItemListInterface {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $this->ensurePopulated();
  }

  /**
   * Computes the calculated values for this item list.
   *
   * In this example, there is only a single item/delta for this field.
   *
   * The ComputedItemListTrait only calls this once on the same instance; from
   * then on, the value is automatically cached in $this->items, for use by
   * methods like getValue().
   */
  protected function ensurePopulated() {
    if (!isset($this->list[0])) {
      $this->list[0] = $this->createItem(0);
    }
  }

}
