<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Toggles the time elements in response to AJAX events.
 *
 * @param array $form
 *   The complete form structure for the current request.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The state object of the form, containing current*/
#[FieldWidget(
  id: 'hel_tpm_service_dates_weekday_and_time_field',
  label: new TranslatableMarkup('Weekday and time field'),
  field_types: ['hel_tpm_service_dates_weekday_and_time_field']
)]
final class WeekdayAndTimeFieldWidget extends WidgetBase {

  /**
   * Weekday array.
   *
   * @var string[]
   */
  public static array $weekdays = [
    'monday' => 'Mon',
    'tuesday' => 'Tue',
    'wednesday' => 'Wed',
    'thursday' => 'Thu',
    'friday' => 'Fri',
    'saturday' => 'Sat',
    'sunday' => 'Sun',
  ];

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $values = $items[$delta]->getValue();

    $parents = $element['#field_parents'];
    $id_prefix = implode('-', array_merge($parents, [$items->getName(), $delta]));
    $wrapper_base = 'edit-' . Html::getUniqueId($id_prefix);

    if (!empty($values)) {
      $values = reset($values);
    }

    foreach (self::$weekdays as $day => $name) {
      $row_wrapper = sprintf('%s-%s', $wrapper_base, $day);

      // Initialize weekday container.
      $row[$day] = [
        '#type' => 'container',
        '#attributes' => ['id' => Html::getId($row_wrapper), 'class' => ['weekday-row']],
      ];

      $default_values = [0 => []];
      if (!empty($values[$day])) {
        $default_values = $values[$day];
      }

      $parent_selector = sprintf('%s-%s', $row_wrapper, 0);
      $row[$day][0] = $this->createTimeSelectElement($name, $parent_selector, $default_values[0], $form_state);

      $selector = sprintf('%s-%s', $row_wrapper, 1);
      if (empty($default_values[1])) {
        $default_values[1] = [];
      }
      $row[$day][1] = $this->createTimeSelectElement('Add', $selector, $default_values[1], $form_state, $parent_selector);
      $row[$day][1]['selector']['#attributes']['class'][] = 'add-time-button';
    }

    $element['value'] = $row;

    // Add shared properties for the element.
    $element['#theme'] = 'hel_tpm_dates_weekday_and_time_field_widget';
    $element['#theme_wrappers'] = ['container', 'form_element'];
    $element['#attributes']['class'] = [
      'container-inline',
      'hel-tpm-service-dates-weekday-and-time-field-elements',
    ];
    $element['#attached']['library'][] = 'hel_tpm_service_dates/hel_tpm_service_dates_weekday_and_time_field';

    $element['#element_validate'][] = [$this, 'validateWeekdayAndTimeFieldWidget'];
    $element['#element_validate'][] = [$this, 'validateTimeSelection'];

    return $element;
  }

  /**
   * Validates the weekday and time field widget.
   *
   * @param array $element
   *   The form element to be validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return void
   *   Void.
   */
  public function validateWeekdayAndTimeFieldWidget(&$element, FormStateInterface $form_state, array &$complete_form) {
    if ($element['#required']) {
      $empty = TRUE;
      foreach (self::$weekdays as $day => $name) {
        if ($element['value'][$day][0]['selector']['#value'] == 1) {
          $empty = FALSE;
        }
      }
      if ($empty) {
        $form_state->setError($element, $this->t('At least one weekday and time field is required.'));
      }
    }
  }

  /**
   * Validates time selection for form elements.
   *
   * This method checks if the start and end time
   * values are provided and ensures
   * they are valid instances of DrupalDateTime. If the validation fails, an
   * error is added to the form state.
   *
   * @param array &$element
   *   The form element being validated, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form being validated.
   * @param array &$complete_form
   *   The complete structure of the form where the element exists,
   *   passed by reference.
   *
   * @return void
   *   Does not return any value. Instead, modifies
   *   the form state to record errors as needed.
   */
  public function validateTimeSelection(&$element, FormStateInterface $form_state, array &$complete_form) {
    $parents = $element['#parents'];
    array_pop($parents);
    $parent_values = NestedArray::getValue($form_state->getValues(), $parents);

    // No need to validate time values.
    if (empty($parent_values['selector']) || $parent_values['selector'] !== 1) {
      return;
    }

    if (empty($element['start']['#value']['object'])) {
      $form_state->setError($element['start'], $this->t('Time is required.'));
      return;
    }
    if (empty($element['end']['#value']['object'])) {
      $form_state->setError($element['end'], $this->t('Time is required.'));
      return;
    }

    $start = $element['start']['#value']['object'];
    $end = $element['end']['#value']['object'];

    if (!$start instanceof DrupalDateTime || !$end instanceof DrupalDateTime) {
      $form_state->setError($element, $this->t('Time is required.'));
      return;
    }

    $start_time = $start->getPhpDateTime();
    $end_time = $end->getPhpDateTime();
    if ($start_time->getTimestamp() > $end_time->getTimestamp()) {
      $form_state->setError($element, $this->t('The start time must be greater than end time.'));
    }
  }

  /**
   * Creates a time select element with a selector and AJAX functionality.
   *
   * @param string $label
   *   The label to display for the selector checkbox.
   * @param string $selector
   *   A unique selector used for identifying the element.
   * @param array $default_values
   *   An array of default values for the element configuration.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object that carries the form's current state.
   * @param string $parent
   *   Time selection element parent if any.
   *
   * @return array
   *   An array structure defining the selector and associated time elements.
   */
  private function createTimeSelectElement(string $label, string $selector, array $default_values, FormStateInterface $form_state, ?string $parent = NULL): array {
    $default_value = !empty($default_values['selector']) ? $default_values['selector'] : NULL;

    $element = [
      'selector' => [
        '#type' => 'checkbox',
        // phpcs:ignore
        '#title' => $this->t($label),
        '#attributes' => [
          'data-selector' => $selector,
          'autocomplete' => 'off',
          'class' => [$default_value == 1 ? 'selected' : NULL],
        ],
        '#default_value' => $default_value,
      ],
    ];

    if (!empty($parent)) {
      $input = sprintf(':input[data-selector="%s"]', $parent);
      $element['selector']['#states'] = [
        'visible' => [$input => ['checked' => TRUE]],
      ];
    }
    $element['time'] = $this->createTimeElement($default_values, $form_state, $selector);

    return $element;
  }

  /**
   * Creates a time element form with conditional visibility and defaults.
   *
   * @param array $values
   *   An array of values, which may include default times for the elements.
   * @param object $form_state
   *   The current state of the form object.
   * @param string $element_id
   *   The ID of the AJAX wrapper element for dynamic re-rendering.
   *
   * @return array
   *   A renderable array structure containing start and end time elements.
   */
  private function createTimeElement($values, $form_state, $element_id): array {
    $input = sprintf(':input[data-selector="%s"]', $element_id);
    $time_element = [
      '#type' => 'datetime',
      '#label' => $this->t('Time'),
      '#date_date_element' => 'none',
      '#date_time_element' => 'text',
      '#date_increment' => '60',
      '#states' => [
        'empty' => [
          $input => ['checked' => FALSE],
        ],
      ],
    ];

    $element = [
      '#type' => 'container',
      '#attributes' => ['id' => $element_id],
      'start' => $time_element,
      'end' => $time_element,
      '#element_validate' => [[$this, 'validateTimeSelection']],
      '#states' => [
        'visible' => [
          $input => ['checked' => TRUE],
        ],
        'empty' => [
          $input => ['checked' => FALSE],
        ],
      ],
    ];

    if (!empty($values['time']['start'])) {
      $element['start']['#default_value'] = $values['time']['start'];
    }

    if (!empty($values['time']['end'])) {
      $element['end']['#default_value'] = $values['time']['end'];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state): array|bool {
    $element = parent::errorElement($element, $error, $form, $form_state);
    if ($element === FALSE) {
      return FALSE;
    }
    $error_property = explode('.', $error->getPropertyPath())[1];
    return $element[$error_property];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    if ($this->ajaxEmptyValuesCheck($form_state)) {
      return ['value' => NULL];
    }

    foreach ($values as &$value) {
      if (empty($value['value'])) {
        continue;
      }
      foreach ($value['value'] as $key => &$row) {
        if ($row[0]['selector'] === 0) {
          unset($value['value'][$key]);
          continue;
        }

        if (!empty($row[1]) && $row[1]['selector'] === 0) {
          unset($row[1]);
        }
      }
    }

    // Make sure dates are in proper format. This fixes issue when field values
    // are saved in incorrect format after failed validation.
    if (!empty($values)) {
      foreach ($values as &$value) {
        if (empty($value['value'])) {
          continue;
        }
        foreach ($value['value'] as &$rows) {
          foreach ($rows as &$row) {
            foreach ($row['time'] as &$time) {
              if (!is_array($time)) {
                continue;
              }
              if (empty($time['object']) || !$time['object'] instanceof DrupalDateTime) {
                continue;
              }
              $time = $time['object'];
            }
          }
        }
      }
    }

    return $values;
  }

  /**
   * Rough fix to empty field values when field ajax dependency isn't met.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   TRUE if the triggering element is not in the defined field values,
   *   FALSE otherwise.
   */
  private function ajaxEmptyValuesCheck(FormStateInterface $form_state): bool {
    $field_values = [
      'start_and_end_date',
      'service_continous',
    ];

    $triggering_element = $form_state->getTriggeringElement();

    if (!empty($triggering_element)) {
      $end = end($triggering_element['#parents']);
      if ($end === 'field_date_selection') {
        return !in_array($triggering_element['#return_value'], $field_values);
      }
    }

    return FALSE;
  }

}
