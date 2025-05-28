<?php

namespace Drupal\hel_tpm_contact_info\Plugin\FieldTypeExport;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\entity_export_csv\Plugin\FieldTypeExportBase;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\hel_tpm_contact_info\Entity\ContactInfo;

/**
 * Defines a Contact Info reference field type export plugin.
 *
 * @FieldTypeExport(
 *   id = "contact_info_reference_export",
 *   label = @Translation("Contact info reference export"),
 *   description = @Translation("Contact info reference export"),
 *   weight = 0,
 *   field_type = {
 *     "entity_reference",
 *   },
 *   entity_type = {},
 *   bundle = {},
 *   field_name = {
 *     "field_contact_info",
 *     "field_contact_info_external",
 *   },
 *   exclusive = FALSE,
 * )
 */
class ContactInfoExport extends FieldTypeExportBase {

  /**
   * {@inheritdoc}
   */
  public function massageExportPropertyValue(FieldItemInterface $field_item, $property_name, FieldDefinitionInterface $field_definition, $options = []) {
    if ($field_item->isEmpty()) {
      return NULL;
    }
    $configuration = $this->getConfiguration();
    if (empty($configuration['format']) || $configuration['format'] !== 'contact_info_fields') {
      return $field_item->get($property_name)->getValue();
    }

    $entity = $field_item->get('entity')->getValue();
    $resultString = '';
    if ($entity instanceof ContactInfo) {
      $resultString = $entity->label();
      if ($entity->hasField('field_municipality')) {
        if (!empty($fieldValue = $entity->get('field_municipality')->getValue())) {
          $resultString .= ' // ' . strip_tags($fieldValue[0]['value']);
        }
      }
    }
    return $resultString;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFormatExportOptions(FieldDefinitionInterface $field_definition) {
    $options = parent::getFormatExportOptions($field_definition);
    $options['contact_info_fields'] = $this->t('Contact info fields');
    return $options;
  }

}
