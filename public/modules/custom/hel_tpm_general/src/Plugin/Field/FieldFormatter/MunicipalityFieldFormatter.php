<?php declare(strict_types = 1);

namespace Drupal\hel_tpm_general\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'Municipality field formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "hel_tpm_general_municipality_field_formatter",
 *   label = @Translation("Municipality field formatter"),
 *   field_types = {"entity_reference"},
 * )
 */
final class MunicipalityFieldFormatter extends EntityReferenceLabelFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = parent::viewElements($items, $langcode);
    $paragraph = $items->getEntity();
    $field_municipality_irrelevant = $paragraph->field_municipality_irrelevant;
    if (empty($field_municipality_irrelevant->value) || $field_municipality_irrelevant->value == 0) {
      return $element;
    }

    // Create render from municipality irrelevant field.
    $render = $field_municipality_irrelevant->view([
      'type' => 'boolean_formatter',
      'label' => 'hidden',
    ]);
    // Alter markup to show title as value instead of on/off
    $render[0]['#markup'] = $render['#title'];
    // Set render to element value.
    $element[0] = $render;

    return $element;
  }

}
