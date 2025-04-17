<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeDefaultWidget;

/**
 * Defines the 'hel_tpm_service_dates_custom_date_range' field widget.
 */
#[FieldWidget(
  id: 'hel_tpm_service_dates_date_range',
  label: new TranslatableMarkup('Custom date range'),
  field_types: ['daterange'],
)]
final class CustomDateRangeWidget extends DateRangeDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    $settings['disable_end_date'] = FALSE;
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $element['disable_end_date'] = [
      '#type' => 'boolean',
      '#title' => new TranslatableMarkup('Disable end date'),
      '#description' => new TranslatableMarkup('Disable end date'),
      '#default_value' => $this->getSetting('disable_end_date'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $value_keys = ['value', 'end_value'];
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    foreach ($value_keys as $key) {
      $value = &$element[$key];
      $value['#date_date_format'] = 'd.m.Y';
      $value['#date_date_element'] = 'text';
      if ($value['#date_time_element'] != 'none') {
        $value['#date_time_element'] = 'text';
        $value['#date_time_format'] = 'H:i';
      }
    }

    if ($this->getSetting('disable_end_date') === TRUE) {
      $element['end_value']['#date_date_element'] = 'none';
    }

    $element['#attached']['library'][] = 'hel_tpm_service_dates/custom_date_range_widget';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateStartEnd(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $start_date = $element['value']['#value']['object'];
    $end_date = &$element['end_value']['#value']['object'];

    if ($this->getSetting('disable_end_date') === TRUE) {
      if ($start_date instanceof DrupalDateTime && $end_date instanceof DrupalDateTime) {
        $end_date->setDate((int) $start_date->format('Y'), (int) $start_date->format('m'), (int) $start_date->format('d'));
      }
    }

    parent::validateStartEnd($element, $form_state, $complete_form);
  }

}
