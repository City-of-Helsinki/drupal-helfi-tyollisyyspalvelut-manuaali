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
    $element = [];
    if (!$items->isEmpty()) {
      return parent::viewElements($items, $langcode);
    }
    $entity = $items->getEntity();
    $element[] = $entity->field_target_group_municipality->view([
      'type' => 'entity_reference_label',
      'label' => 'hidden'
    ]);
    return $element;
  }

}
