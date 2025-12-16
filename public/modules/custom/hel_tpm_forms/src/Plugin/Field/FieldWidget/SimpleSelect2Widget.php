<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_forms\Plugin\Field\FieldWidget;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the 'hel_tpm_forms_simple_select2' field widget.
 */
#[FieldWidget(
  id: 'hel_tpm_forms_simple_select2',
  label: new TranslatableMarkup('Simple Select2'),
  field_types: [
    'entity_reference',
    'list_integer',
    'list_float',
    'list_string',
  ],
  multiple_values: TRUE,
)]
final class SimpleSelect2Widget extends OptionsSelectWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#attached']['library'][] = 'hel_tpm_forms/simple_select2';
    $settings = [
      'theme' => \Drupal::theme()->getActiveTheme()->getName(),
    ];
    $element['#attributes']['class'][] = 'simple-select2-widget';
    $element['#attributes']['data-simple-select2-config'] = Json::encode($settings);
    return $element;
  }

}
