<?php

namespace Drupal\hel_tpm_general\Plugin\FieldTypeExport;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\entity_export_csv\Plugin\FieldTypeExportBase;

/**
 * Defines a default field type export plugin.
 *
 * @FieldTypeExport(
 *   id = "plaintext_export",
 *   label = @Translation("Plain text export"),
 *   description = @Translation("Export all text as plain text export"),
 *   weight = 100,
 *   field_type = {
 *     "text_long"
 *   },
 *   entity_type = {},
 *   bundle = {},
 *   field_name = {},
 *   exclusive = FALSE,
 * )
 */
class PlainTextExport extends FieldTypeExportBase {

  /**
   * {@inheritdoc}
   */
  public function massageExportPropertyValue(
    FieldItemInterface $field_item,
    $property_name,
    FieldDefinitionInterface $field_definition,
    $options = [],
  ) {
    $value = parent::massageExportPropertyValue($field_item, $property_name, $field_definition, $options);
    return strip_tags($value);
  }

}
