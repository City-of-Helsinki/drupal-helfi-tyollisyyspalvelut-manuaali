<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;
use Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeDefaultWidget;

/**
 * Defines the 'hel_tpm_service_dates_custom_date_range' field widget.
 */
#[FieldWidget(
  id: 'hel_tpm_service_dates_date_range',
  label: new TranslatableMarkup('Custom date range'),
  field_types: ['daterange'],
)]
final class CustomDateRangeWidget extends DateRangeDefaultWidget implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'disable_end_date' => FALSE,
      'display_time_label' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['disable_end_date'] = [
      '#type' => 'checkbox',
      '#title' => new TranslatableMarkup('Disable end date'),
      '#description' => new TranslatableMarkup('Disable end date'),
      '#default_value' => $this->getSetting('disable_end_date'),
    ];
    $element['display_time_label'] = [
      '#type' => 'checkbox',
      '#title' => new TranslatableMarkup('Display time label'),
      '#description' => new TranslatableMarkup('Display time label'),
      '#default_value' => $this->getSetting('display_time_label'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    if ($this->getSetting('disable_end_date')) {
      $summary[] = new TranslatableMarkup('End date disabled');
    }
    if ($this->getSetting('display_time_label')) {
      $summary[] = new TranslatableMarkup('Display time label');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $value_keys = ['value' => $this->t('Start Time'), 'end_value' => $this->t('End Time')];
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    foreach ($value_keys as $key => $label) {
      $value = &$element[$key];
      $value['#date_date_format'] = 'd.m.Y';
      $value['#date_date_element'] = 'text';
      if ($value['#date_time_element'] != 'none') {
        $value['#date_time_element'] = 'text';
        $value['#date_time_format'] = 'H:i:s';
      }

      if ($this->getSetting('display_time_label')) {
        $value['#time_title'] = $label;
        $value['#date_time_callbacks'] = [[$this, 'timeLabelCallbackTrusted']];
      }

    }

    if ($this->getSetting('disable_end_date') === TRUE) {
      $element['end_value']['#date_date_element'] = 'none';
      $element['end_value']['#title'] = NULL;
      $element['#attributes']['class'][] = 'custom-date-range-end-date-disabled';
    }

    $element['#attached']['library'][] = 'hel_tpm_service_dates/custom_date_range_widget';

    return $element;
  }

  /**
   * Set labels for time element.
   */
  public static function timeLabelCallbackTrusted(array &$element, FormStateInterface $form_state, ?DrupalDateTime $date = NULL) {
    if ($element['#time_title']) {
      $element['time']['#title'] = $element['#time_title'];
      $element['time']['#title_display'] = 'before';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $keys = ['value', 'end_value'];
    $values = parent::massageFormValues($values, $form, $form_state);
    $datetime_type = $this->getFieldSetting('datetime_type');
    if ($datetime_type === DateRangeItem::DATETIME_TYPE_DATE) {
      $storage_format = DateTimeItemInterface::DATE_STORAGE_FORMAT;
    }
    else {
      $storage_format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    }
    foreach ($values as &$value) {
      foreach ($keys as $key) {
        if (is_array($value[$key])) {
          if (empty($value[$key]['object'])) {
            $value[$key] = NULL;
          }
          else {
            $object = $value[$key]['object']->getPhpDateTime();
            $value[$key] = $object->format($storage_format);
          }
        }
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function validateStartEnd(array &$element, FormStateInterface $form_state, array &$complete_form) {
    if (empty($element['value']['#value']['object']) || empty($element['end_value']['#value']['object'])) {
      return;
    }
    $start_date = $element['value']['#value']['object'];
    $end_date = &$element['end_value']['#value']['object'];

    if ($this->getSetting('disable_end_date') === TRUE) {
      if ($start_date instanceof DrupalDateTime && $end_date instanceof DrupalDateTime) {
        $end_date->setDate((int) $start_date->format('Y'), (int) $start_date->format('m'), (int) $start_date->format('d'));
      }
    }

    parent::validateStartEnd($element, $form_state, $complete_form);
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['timeLabelCallbackTrusted'];
  }

}
