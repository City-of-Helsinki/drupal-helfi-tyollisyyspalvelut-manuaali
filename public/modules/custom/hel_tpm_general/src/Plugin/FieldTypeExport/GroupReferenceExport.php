<?php
namespace Drupal\hel_tpm_general\Plugin\FieldTypeExport;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\entity_export_csv\Plugin\FieldTypeExport\EntityReferenceExport;
use Drupal\entity_export_csv\Plugin\FieldTypeExportBase;
use Drupal\group\Entity\GroupRelationship;

/**
 * Defines a default field type export plugin.
 *
 * @FieldTypeExport(
 *   id = "group_reference_export",
 *   label = @Translation("Group reference export"),
 *   description = @Translation("Group reference export"),
 *   weight = 100,
 *   field_type = {
 *     "entitygroupfield"
 *   },
 *   entity_type = {},
 *   bundle = {},
 *   field_name = {},
 *   exclusive = FALSE,
 * )
 */
class GroupReferenceExport extends EntityReferenceExport {
  public function massageExportPropertyValue(FieldItemInterface $field_item, $property_name, FieldDefinitionInterface $field_definition, $options = []) {
    // If this entity/bundle has no group relation type plugins enabled,
    // there's no way there could be any group associations, so exit early.
    if (!entitygroupfield_get_group_relation_type_plugin_ids($field_item->getEntity()->getEntityTypeId(), $field_item->getEntity()->bundle())) {
      return NULL;
    }

    $group_relationships = GroupRelationship::loadByEntity($field_item->getEntity());
    if (empty($group_relationships)) {
      return NULL;
    }

    $result = [];
    foreach ($group_relationships as $delta => $group_relationship) {
      $result[] = $group_relationship->getGroup()->label();
    }
    return implode('|', $result);
  }

}