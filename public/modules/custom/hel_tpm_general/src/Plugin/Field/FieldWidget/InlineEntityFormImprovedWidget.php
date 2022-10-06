<?php

namespace Drupal\hel_tpm_general\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormBase;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormComplex;

/**
 * Defines the 'hel_tpm_general_inline_entity_form_improved' field widget.
 *
 * @FieldWidget(
 *   id = "hel_tpm_general_inline_entity_form_improved",
 *   label = @Translation("Inline entity form improved"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions",
 *   },
 * )
 */
class InlineEntityFormImprovedWidget extends InlineEntityFormComplex {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $target_type = $this->getFieldSetting('target_type');
    // Build a parents array for this element's values in the form.
    $parents = array_merge($element['#field_parents'], [
      $items->getName(),
      'form',
    ]);
    $entities = $this->getEntities($form_state);
    $new_key = $entities ? max(array_keys($entities)) + 1 : 0;

    unset($element['actions']['ief_add_existing']);

    $ief_id = $form_state->get(['inline_entity_form', $this->getIefId(), 'form']);
    if ($ief_id === NULL && !$this->cardinalityReached($form_state)) {
      $element['form'] = [
        '#type' => 'fieldset',
        '#attributes' => ['class' => ['ief-form', 'ief-form-bottom']],
        // Identifies the IEF widget to which the form belongs.
        '#ief_id' => $this->getIefId(),
        // Used by Field API and controller methods to find the relevant
        // values in $form_state.
        '#parents' => array_merge($parents, [$new_key]),
        '#entity_type' => $target_type,
        '#ief_labels' => $this->getEntityTypeLabels(),
        '#match_operator' => $this->getSetting('match_operator'),
      ];
      $element['form'] += inline_entity_form_reference_form($element['form'], $form_state);
    }

    unset($element['form']['actions']['ief_reference_cancel']);

    if (!empty($entities)) {
      $this->disableEdit($element, count($entities));
    }

    return $element;
  }

  /**
   * @param $element
   * @param int $entity_count
   *
   * @return void|null
   */
  protected function disableEdit(&$element, int $entity_count) {
    if (empty($element['entities'])) {
      return NULL;
    }

    for ($i = 0; $i < $entity_count; $i++) {
      $entity = &$element['entities'][$i];
      if ($entity['#needs_save'] === FALSE) {
        unset($entity['actions']['ief_entity_edit']);
      }
    }
  }

  /**
   * @param $form_state
   *
   * @return bool
   */
  protected function cardinalityReached(FormStateInterface $form_state) {
    $entities = $this->getEntities($form_state);
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    return ($cardinality > 0 && count($entities) == $cardinality);
  }

  /**
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  protected function getEntities(FormStateInterface $form_state) {
    return $form_state->get(['inline_entity_form', $this->getIefId(), 'entities']);
  }

}
