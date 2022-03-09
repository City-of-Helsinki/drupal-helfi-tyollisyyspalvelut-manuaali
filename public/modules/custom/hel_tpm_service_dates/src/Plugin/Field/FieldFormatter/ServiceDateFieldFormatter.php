<?php

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'ServiceDateField' formatter.
 *
 * @FieldFormatter(
 *   id = "hel_tpm_service_dates_service_date_field",
 *   label = @Translation("ServiceDateField"),
 *   field_types = {
 *     "paragraph"
 *   }
 * )
 */
class ServiceDateFieldFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#markup' => $item->value,
      ];
    }

    return $element;
  }

}
