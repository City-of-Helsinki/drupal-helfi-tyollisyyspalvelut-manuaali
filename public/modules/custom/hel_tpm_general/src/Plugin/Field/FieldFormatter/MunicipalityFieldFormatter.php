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
    if (!empty($element)) {
      return $element;
    }
    $element[] = [
      '#markup' => $this->t('Municipality doesn\'t matter'),
    ];
    return $element;
  }

}
