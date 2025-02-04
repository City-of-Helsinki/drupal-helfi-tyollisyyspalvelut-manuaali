<?php

namespace Drupal\views_exposed_embed\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Provides a field item list implementation for views exposed embed fields.
 */
class ViewsExposedEmbedFieldItemList extends FieldItemList {

  /**
   * Compares two field item lists to determine if they are equal.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $list_to_compare
   *   The field item list to compare with the current field item list.
   *
   * @return bool
   *   TRUE if the field item lists are equal, FALSE otherwise.
   */
  public function equals(FieldItemListInterface $list_to_compare) {
    $count1 = count($this);
    $count2 = count($list_to_compare);
    if ($count1 === 0 && $count2 === 0) {
      // Both are empty we can safely assume that it did not change.
      return TRUE;
    }
    if ($count1 !== $count2) {
      // The number of items is different so they do not have the same values.
      return FALSE;
    }

    // The map field type does not have any property defined (because they are
    // dynamic), so the only way to evaluate the equality for it is to rely
    // solely on its values.
    $value1 = $this->getValue();
    $value2 = $list_to_compare->getValue();

    return $this->recursiveArrayEquals($value1, $value2);
  }

  /**
   * Recursively compares two arrays for equality.
   *
   * @param array $array1
   *   The first array to compare.
   * @param array $array2
   *   The second array to compare.
   *
   * @return bool
   *   TRUE if the arrays are equal, FALSE otherwise.
   */
  private function recursiveArrayEquals(array $array1, array $array2): bool {
    if (count($array1) !== count($array2)) {
      return FALSE;
    }

    foreach ($array1 as $key => $value) {
      if (!array_key_exists($key, $array2)) {
        return FALSE;
      }
      if (is_array($value) && is_array($array2[$key])) {
        if (!$this->recursiveArrayEquals($value, $array2[$key])) {
          return FALSE;
        }
      }
      elseif ($value !== $array2[$key]) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
