<?php

namespace Drupal\hel_tpm_general\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'municipality_specific_ief_widget' widget.
 *
 * @FieldWidget(
 *   id = "municipality_specific_ief_widget",
 *   label = @Translation("Municipality specific paragraph ief"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions",
 *   },
 *   multiple_values = true
 * )
 */
class InlineEntityFormComplexMunicipalitySpecificWidget extends InlineEntityFormComplexNoConfirmWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $this->relabelEntities($element['entities']);
    return $element;
  }

  /**
   * Helper method to relabel widget entities.
   *
   * @param array $entities
   *   Array of entities.
   */
  protected function relabelEntities(array &$entities) {
    foreach ($entities as &$row) {
      if (empty($row['#entity'])) {
        continue;
      }
      $municipality = $row['#entity']->field_municipality->entity;
      if (empty($municipality)) {
        continue;
      }
      $row['#label'] = $municipality->label();
    }
  }

}
