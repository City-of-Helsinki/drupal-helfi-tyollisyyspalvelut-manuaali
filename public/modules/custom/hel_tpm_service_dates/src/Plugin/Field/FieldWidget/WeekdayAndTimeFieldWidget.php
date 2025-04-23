<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
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

    if (!empty($values)) {
      $values = reset($values);
    }

    foreach (self::$weekdays as $day => $name) {
      $wrapper_id = sprintf('%s-%s', $day, $delta);
      // Initialize weekday container.
      $row[$day] = [
        '#type' => 'container',
        '#attributes' => ['id' => $wrapper_id, 'class' => ['weekday-row']],
      ];

      $default_values = [0 => []];
      if (!empty($values[$day])) {
        $default_values = $values[$day];
      }

      $selector = sprintf('%s-%s-%s', $day, $delta, 0);
      $row[$day][0] = $this->createTimeSelectElement($name, $selector, $default_values[0], $form_state, $wrapper_id);

      if ($this->showSecondTimeElement($default_values, $form_state, $selector)) {
        $selector = sprintf('%s-%s-%s', $day, $delta, 1);
        if (empty($default_values[1])) {
          $default_values[1] = [];
        }
        $row[$day][1] = $this->createTimeSelectElement('Add', $selector, $default_values[1], $form_state, $wrapper_id);
        $row[$day][1]['selector']['#attributes']['class'][] = 'add-time-button';
      }
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
   * Determines if the second time element should be displayed.
   *
   * @param array $values
   *   The array containing the values to check for the selector.
   * @param object $form_state
   *   The form state object providing state and triggering element data.
   * @param string $parent_selector
   *   The selector used to identify the triggering element.
   *
   * @return bool
   *   TRUE if the second time element should be displayed, FALSE otherwise.
   */
  private function showSecondTimeElement($values, $form_state, $parent_selector) {
    $triggering_element = $form_state->getTriggeringElement();
    if (!empty($triggering_element) && !empty($triggering_element['#attributes']['data-selector']) && $triggering_element['#attributes']['data-selector'] === $parent_selector) {
      return (bool) $triggering_element['#value'];
    }
    if (empty($values[0]['selector'])) {
      return FALSE;
    }
    return (bool) $values[0]['selector'];
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
   * @param string $ajax_wrapper
   *   The identifier of the wrapper element for AJAX updates.
   *
   * @return array
   *   An array structure defining the selector and associated time elements.
   */
  private function createTimeSelectElement(string $label, string $selector, array $default_values, FormStateInterface $form_state, string $ajax_wrapper): array {
    $element = [
      'selector' => [
        '#type' => 'checkbox',
        // phpcs:ignore
        '#title' => $this->t($label),
        '#attributes' => [
          'data-selector' => $selector,
          'autocomplete' => 'off',
        ],
        '#default_value' => !empty($default_values['selector']) ? $default_values['selector'] : NULL,
        '#ajax' => [
          'wrapper' => $ajax_wrapper,
          'event' => 'change',
          'callback' => [$this, 'toggleTimeAjax'],
        ],
      ],
    ];

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
   * @param string $ajax_wrapper_id
   *   The ID of the AJAX wrapper element for dynamic re-rendering.
   *
   * @return array
   *   A renderable array structure containing start and end time elements.
   */
  private function createTimeElement($values, $form_state, $ajax_wrapper_id): array {
    $enabled = $this->showTimeElements($values, $form_state, $ajax_wrapper_id);

    $time_element = [
      '#type' => 'datetime',
      '#label' => $this->t('Time'),
      '#date_date_element' => 'none',
      '#date_time_element' => 'text',
      '#date_increment' => '60',
      '#access' => $enabled,
      '#required' => $enabled,
    ];

    $element = [
      '#type' => 'container',
      '#attributes' => ['id' => $ajax_wrapper_id],
      'start' => $time_element,
      'end' => $time_element,
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
   * Determines if time elements should be enabled based on provided values.
   *
   * @param array $values
   *   An array of values used to determine the state of the time elements.
   * @param object $form_state
   *   The state object of the form, containing data and the triggering element.
   * @param string $selector
   *   The selector key used to identify the relevant data in
   *   values or the triggering element.
   *
   * @return bool
   *   TRUE if the time elements should be enabled, FALSE otherwise.
   */
  private function showTimeElements($values, $form_state, $selector) {
    $enabled = FALSE;
    if (!empty($values)) {
      $enabled = (bool) $values['selector'];
    }
    $triggering_element = $form_state->getTriggeringElement();
    if (!empty($triggering_element['data-selector']) && $triggering_element['data-selector'] === $selector) {
      $enabled = (bool) $triggering_element['#value'];
    }
    return $enabled;
  }

  /**
   * Toggles the time element via an AJAX callback.
   *
   * @param array $form
   *   The complete form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An object that holds the current state of the form.
   *
   * @return array
   *   The form element being toggled.
   */
  public function toggleTimeAjax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    array_pop($triggering_element['#array_parents']);
    array_pop($triggering_element['#array_parents']);
    $element = NestedArray::getValue($form, $triggering_element['#array_parents']);
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
