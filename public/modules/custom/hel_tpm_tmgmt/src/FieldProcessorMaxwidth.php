<?php

namespace Drupal\hel_tpm_tmgmt;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\tmgmt_content\DefaultFieldProcessor;

/**
 * Overrides the field processor to add maxlength JavaScript configuration.
 *
 * This class extends the default field processor to include translatable
 * data enhancements by applying maxlength JavaScript settings where
 * applicable.
 */
class FieldProcessorMaxwidth extends DefaultFieldProcessor {

  /**
   * Extracts translatable data from a field with maxlength settings applied.
   *
   * This method extends the parent implementation by adding maxlength
   * JavaScript configuration to translatable properties in the field data.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list object from which translatable data is extracted.
   *
   * @return array
   *   An array of translatable data, with maxlength JavaScript settings
   *   applied to relevant properties where applicable.
   */
  public function extractTranslatableData(FieldItemListInterface $field) {
    $data = parent::extractTranslatableData($field);

    $max_length_js = $this->getMaxLengthJs($field);
    if (empty($max_length_js)) {
      return $data;
    }

    foreach ($field as $delta => $field_item) {
      foreach ($data[$delta] as &$property_value) {
        if (empty($property_value['#translate']) || $property_value['#translate'] !== TRUE) {
          continue;
        }

        $property_value['#maxlength_js_enabled'] = TRUE;
        $property_value['#max_length'] = $max_length_js;
      }
      unset($property_value);
    }
    return $data;
  }

  /**
   * Retrieves the maximum length JavaScript setting for a given field.
   *
   * This method extracts the maxlength JavaScript configuration from the
   * field's form display settings.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list object for which the maxlength setting
   *   needs to be retrieved.
   *
   * @return string|null
   *   The JavaScript maxlength value if defined, or NULL if not set.
   */
  protected function getMaxLengthJs(FieldItemListInterface $field) {
    $entity = $field->getEntity();
    $form_display = EntityFormDisplay::load($entity->getEntityTypeId() . '.' . $entity->bundle() . '.default');
    if (empty($form_display)) {
      return NULL;
    }
    $component = $form_display->getComponent($field->getName());
    return $component['third_party_settings']['maxlength']['maxlength_js'] ?? NULL;
  }

}
