<?php

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs_asymmetric_translation_widgets\Plugin\Field\FieldWidget\ParagraphsClassicAsymmetricWidget;

/**
 * Defines the 'hel_tpm_service_dates_service_time_and_place_widget' widget.
 *
 * @FieldWidget(
 *   id = "hel_tpm_service_dates_service_time_and_place_widget",
 *   label = @Translation("Service time and place widget"),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class ServiceTimeAndPlaceWidgetWidget extends ParagraphsClassicAsymmetricWidget {

  /**
   * Dependency mapping array.
   *
   * @var array[]
   */
  public static array $dependencyMapping = [
    'field_start_and_end_date' => [
      'start_and_end_date',
    ],
    'field_weekday_and_time' => [
      'start_and_end_date',
      'service_continous',
    ],
    'field_date' => [
      'separate_dates',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['top']['links']['remove_button']['#paragraphs_mode'] = 'removed';

    $this->createFieldStatesRules($element);

    return $element;
  }

  /**
   * Add field state rules for mapped fields.
   *
   * @param array $element
   *   Widget element.
   *
   * @return void
   *   -
   */
  protected function createFieldStatesRules(array &$element) {
    if (empty($element['subform']['field_date_selection'])) {
      return;
    }
    $parents = $element['subform']['field_date_selection']['widget']['#parents'];

    $selector = $root = array_shift($parents);
    if ($parents) {
      $selector = $root . '[' . implode('][', $parents) . ']';
    }

    foreach (self::$dependencyMapping as $field => $dependencies) {
      foreach ($dependencies as $dependency) {
        $input = sprintf(':input[name="%s"]', $selector);
        $element['subform'][$field]['#states']['visible'][$input][] = ['value' => $dependency];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function elementValidate($element, FormStateInterface $form_state, $form) {
    $this->clearUnselectedDateFields($element, $form_state);
    $this->validateRequiredDependencyFields($element, $form_state);
    parent::elementValidate($element, $form_state, $form);
  }

  /**
   * Clear unselected date fields using dependency mapping.
   *
   * @param array $element
   *   Paragraph widget element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return void
   *   -
   */
  protected function clearUnselectedDateFields(array $element, FormStateInterface &$form_state) {
    $values = $form_state->getValues();

    $controller = $element['subform']['field_date_selection'];
    if (empty($controller)) {
      return;
    }
    $parent_element = $controller['widget']['#field_parents'];
    $parents = array_merge($parent_element, [
      'field_date_selection',
      0,
      'value',
    ]);

    $dependency_value = NestedArray::getValue($values, $parents);

    if (empty($dependency_value)) {
      return;
    }

    foreach (self::$dependencyMapping as $field => $dependencies) {
      if (in_array($dependency_value, $dependencies)) {
        continue;
      }
      $parents = $element['subform'][$field]['widget']['#parents'];

      // If dependency isn't selected set field value to empty.
      $form_state->setValue($parents, []);
    }
  }

  /**
   * Additional validation for dependent date field.
   *
   * @param array $element
   *   Widget element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return void
   *   -
   */
  protected function validateRequiredDependencyFields(array $element, FormStateInterface &$form_state) {
    $values = $form_state->getValues();
    if (empty($element['subform']['field_date_selection'])) {
      return;
    }

    $controller = $element['subform']['field_date_selection'];
    $parent_element = $controller['widget']['#field_parents'];
    $parents = array_merge($parent_element, ['field_date_selection', 0, 'value']);

    $controller_value = NestedArray::getValue($values, $parents);

    if (empty($controller_value)) {
      return;
    }

    foreach (self::$dependencyMapping as $field => $dependencies) {

      if (!in_array($controller_value, $dependencies)) {
        continue;
      }

      $element_keys = array_merge($parent_element, [$field, 0, 'value']);
      $field_value = NestedArray::getValue($values, $element_keys);
      $valid = FALSE;

      if ($field === 'field_weekday_and_time') {
        $message = $this->t('At least one weekday and time field is required.');
        foreach ($field_value as $row) {
          if ($row[0]['selector'] == 1) {
            $valid = TRUE;
            break;
          }
        }
      }
      else {
        $valid = !empty($field_value);
      }
      if (!$valid) {
        $error_element = $element['subform'][$field]['widget'];
        if (empty($message)) {
          $message = $this->t('@name field is required.', ['@name' => $error_element['#title']]);
        }
        $form_state->setError($error_element, $message);
      }
    }
  }

}
