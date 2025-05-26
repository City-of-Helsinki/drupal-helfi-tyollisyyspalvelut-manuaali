<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
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
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $is_multiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();
    $is_unlimited_not_programmed = FALSE;
    $parents = $form['#parents'];

    // Determine the number of widgets to display.
    switch ($cardinality) {
      case FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED:
        $field_state = static::getWidgetState($parents, $field_name, $form_state);
        // Disable automatically appending
        // new row when editing old content with values.
        $max = $field_state['items_count'] > 0 ? $field_state['items_count'] - 1 : 0;

        $is_unlimited_not_programmed = !$form_state->isProgrammed();
        break;

      default:
        $max = $cardinality - 1;
        break;
    }

    $title = $this->fieldDefinition->getLabel();
    $description = $this->getFilteredDescription();
    $id_prefix = implode('-', array_merge($parents, [$field_name]));
    $wrapper_id = Html::getUniqueId($id_prefix . '-add-more-wrapper');

    $elements = [];

    // This whole method is because we don't want to create new row by default.
    for ($delta = 0; $delta <= $max; $delta++) {
      // Add a new empty item if it doesn't exist yet at this delta.
      if (!isset($items[$delta])) {
        $items->appendItem();
      }

      // For multiple fields, title and description are handled by the wrapping
      // table.
      if ($is_multiple) {
        $element = [
          '#title' => $this->t('@title (value @number)', ['@title' => $title, '@number' => $delta + 1]),
          '#title_display' => 'invisible',
          '#description' => '',
        ];
      }
      else {
        $element = [
          '#title' => $title,
          '#title_display' => 'before',
          '#description' => $description,
        ];
      }

      $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);

      if ($element) {
        // Input field for the delta (drag-n-drop reordering).
        if ($is_multiple) {
          // We name the element '_weight' to avoid clashing with elements
          // defined by widget.
          $element['_weight'] = [
            '#type' => 'weight',
            '#title' => $this->t('Weight for row @number', ['@number' => $delta + 1]),
            '#title_display' => 'invisible',
            // Note: this 'delta' is the FAPI #type 'weight' element's property.
            '#delta' => $max,
            '#default_value' => $items[$delta]->_weight ?: $delta,
            '#weight' => 100,
          ];

          // Add 'remove' button, if not working with a programmed form.
          if ($is_unlimited_not_programmed) {
            $remove_button = [
              '#delta' => $delta,
              '#name' => str_replace('-', '_', $id_prefix) . "_{$delta}_remove_button",
              '#type' => 'submit',
              '#value' => $this->t('Remove'),
              '#validate' => [],
              '#submit' => [[static::class, 'deleteSubmit']],
              '#limit_validation_errors' => [],
              '#ajax' => [
                'callback' => [static::class, 'deleteAjax'],
                'wrapper' => $wrapper_id,
                'effect' => 'fade',
              ],
            ];

            $element['_actions'] = [
              'delete' => $remove_button,
              '#weight' => 101,
            ];
          }
        }

        $elements[$delta] = $element;
      }
    }

    if ($elements) {
      $elements += [
        '#theme' => 'field_multiple_value_form',
        '#field_name' => $field_name,
        '#cardinality' => $cardinality,
        '#cardinality_multiple' => $is_multiple,
        '#required' => $this->fieldDefinition->isRequired(),
        '#title' => $title,
        '#description' => $description,
        '#max_delta' => $max,
      ];

      // Add 'add more' button, if not working with a programmed form.
      if ($is_unlimited_not_programmed) {
        $elements['#prefix'] = '<div id="' . $wrapper_id . '">';
        $elements['#suffix'] = '</div>';

        $elements['add_more'] = [
          '#type' => 'submit',
          '#name' => strtr($id_prefix, '-', '_') . '_add_more',
          '#value' => $this->t('Add another item'),
          '#attributes' => ['class' => ['field-add-more-submit']],
          '#limit_validation_errors' => [],
          '#submit' => [[static::class, 'addMoreSubmit']],
          '#ajax' => [
            'callback' => [static::class, 'addMoreAjax'],
            'wrapper' => $wrapper_id,
            'effect' => 'fade',
          ],
        ];
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $time_labels = ['value' => $this->t('Start Time'), 'end_value' => $this->t('End Time')];
    $value_keys = ['value', 'end_value'];
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    foreach ($value_keys as $key) {
      $value = &$element[$key];
      $value['#date_date_format'] = 'd.m.Y';
      $value['#date_date_element'] = 'text';
      if ($value['#date_time_element'] != 'none') {
        $value['#date_time_element'] = 'text';
        $value['#date_time_format'] = 'H:i:s';
      }

      if ($this->getSetting('display_time_label')) {
        $value['#time_title'] = $time_labels[$key];
        $value['#date_time_callbacks'] = [[$this, 'timeLabelCallbackTrusted']];
      }

    }

    if ($this->getSetting('disable_end_date') === TRUE) {
      $element['end_value']['#date_date_element'] = 'none';
      $element['end_value']['#title_display'] = 'invisible';
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
