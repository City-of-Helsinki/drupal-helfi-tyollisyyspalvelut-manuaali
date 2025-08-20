<?php

namespace Drupal\hel_tpm_general\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormComplex;

/**
 * Defines the 'ief_no_confirm_widget' widget.
 *
 * @FieldWidget(
 *   id = "ief_no_confirm_widget",
 *   label = @Translation("IEF without remove confirmation"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions",
 *   },
 *   multiple_values = true
 * )
 */
class InlineEntityFormComplexNoConfirmWidget extends InlineEntityFormComplex {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $defaults = parent::defaultSettings();
    $defaults += [
      'prevent_translation_deletion' => FALSE,
    ];

    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $element['prevent_translation_deletion'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Prevent reference deletion from translations'),
      '#default_value' => $this->getSetting('prevent_translation_deletion'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    if ($this->getSetting('prevent_translation_deletion')) {
      $summary[] = $this->t('Prevent reference deletion from translations');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $this->alterEntityRemove($element, $items, $form_state);
    $this->doPreventTranslationReferenceDeletion($element, $items);
    return $element;
  }

  /**
   * Prevents the deletion of entity references in non-default translations.
   *
   * @param array $element
   *   The render array for the field.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field item list interface containing the field's items.
   *
   * @return void
   *   No return value as the method modifies the passed array by reference.
   */
  protected function doPreventTranslationReferenceDeletion(array &$element, FieldItemListInterface $items): void {
    if (empty($this->getSetting('prevent_translation_deletion'))) {
      return;
    }

    $entity = $items->getEntity();
    if (!$entity || $entity->isDefaultTranslation()) {
      return;
    }

    if (empty($element['entities']) || !is_array($element['entities'])) {
      return;
    }

    foreach ($element['entities'] as &$entity_element) {
      if (!isset($entity_element['actions']) || !is_array($entity_element['actions'])) {
        continue;
      }
      // Handle common IEF action button keys.
      if (isset($entity_element['actions']['ief_entity_remove'])) {
        $entity_element['actions']['ief_entity_remove']['#access'] = FALSE;
      }
      if (isset($entity_element['actions']['remove'])) {
        $entity_element['actions']['remove']['#access'] = FALSE;
      }
    }
    unset($entity_element);
  }

  /**
   * Alter entity removal button to delete entity without confirmation.
   *
   * @param array $element
   *   Field element array.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Form item list interface.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface.
   *
   * @return void
   *   Void.
   */
  protected function alterEntityRemove(array &$element, FieldItemListInterface $items, FormStateInterface $form_state) {
    // Build a parents array for this element's values in the form.
    $widget_state = $form_state->get(['inline_entity_form', $element['#ief_id']]);

    $entities = !empty($widget_state['entities']) ? $widget_state['entities'] : [];
    if (empty($entities)) {
      return;
    }
    foreach ($widget_state['entities'] as $i => $row) {
      $delta = $element['#ief_id'] . '-' . $i;
      $parents = [
        $items->getName(),
        'form',
        'entities', $delta, 'form',
      ];

      $element['entities'][$i]['actions']['ief_entity_remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'ief-entity-remove-' . $delta,
        '#limit_validation_errors' => [$parents],
        '#ajax' => [
          'callback' => 'inline_entity_form_get_element',
          'wrapper' => 'inline-entity-form-' . $element['#ief_id'],
        ],
        '#allow_existing' => $this->getSetting('allow_existing'),
        '#removed_reference' => $this->getSetting('removed_reference'),
        '#submit' => [[get_class($this), 'submitRemoveEntity']],
        '#ief_row_delta' => $i,
      ];
    }
  }

  /**
   * Remove form submit callback.
   *
   * The row is identified by #ief_row_delta stored on the triggering
   * element.
   * This isn't an #element_validate callback to avoid processing the
   * remove form when the main form is submitted.
   *
   * @param array $form
   *   The complete parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   */
  public static function submitRemoveEntity(array $form, FormStateInterface $form_state) {
    $element = inline_entity_form_get_element($form, $form_state);
    $remove_button = $form_state->getTriggeringElement();
    $delta = $remove_button['#ief_row_delta'];

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $element['entities'][$delta]['#entity'];
    $entity_id = $entity->id();

    $form_values = NestedArray::getValue($form_state->getValues(), $element['entities'][$delta]['#parents']);

    $form_state->setRebuild();

    $widget_state = $form_state->get([
      'inline_entity_form',
      $element['#ief_id'],
    ]);

    // The entity hasn't been saved yet, or is being deleted,
    // so remove the reference.
    unset($widget_state['entities'][$delta]);

    // If the entity has been saved, delete it if either the widget is set to
    // always delete, or the widget is set to let the user decide and the user
    // has decided to delete.
    if ($entity_id) {
      $removed_reference = $remove_button['#removed_reference'];
      if ($removed_reference === self::REMOVED_DELETE || ($removed_reference === self::REMOVED_OPTIONAL && $form_values['delete'] === 1)) {
        $widget_state['delete'][] = $entity;
      }
    }
    $form_state->set(['inline_entity_form', $element['#ief_id']], $widget_state);
  }

}
