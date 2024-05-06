<?php

declare(strict_types = 1);

namespace Drupal\hel_tpm_general\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\options\Plugin\Field\FieldFormatter\OptionsDefaultFormatter;

/**
 * Plugin implementation of the 'Age group field' formatter.
 *
 * @FieldFormatter(
 *   id = "hel_tpm_general_age_group_field_formatter",
 *   label = @Translation("Age group field"),
 *   field_types = {"list_string"},
 * )
 */
final class AgeGroupFieldFormatter extends OptionsDefaultFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    // Field value is selected return rendered field.
    if (!$items->isEmpty()) {
      return parent::viewElements($items, $langcode);
    }
    $entity = $items->getEntity();
    // If entity is not found return default value.
    if (empty($entity)) {
      return parent::viewElements($items, $langcode);
    }
    // Render age field for element.
    $element[0] = $entity->field_age->view([
      'type' => 'range_integer',
      'label' => 'hidden',
      'settings' => ['field_prefix_suffix' => 1],
    ]);
    return $element;
  }

}
