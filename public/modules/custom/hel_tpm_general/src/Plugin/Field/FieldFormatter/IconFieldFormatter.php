<?php

namespace Drupal\hel_tpm_general\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'Icon field' formatter.
 *
 * @FieldFormatter(
 *   id = "hel_tpm_general_icon_field",
 *   label = @Translation("Icon field"),
 *   field_types = {
 *     "list_string"
 *   }
 * )
 */
class IconFieldFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#theme' => 'hel_tpm_general_icon_field',
        '#icon' => $item->value,
      ];
    }

    return $element;
  }

}
