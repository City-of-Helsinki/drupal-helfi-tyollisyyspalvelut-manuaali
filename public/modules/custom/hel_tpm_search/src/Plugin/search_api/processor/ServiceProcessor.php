<?php

namespace Drupal\hel_tpm_search\Plugin\search_api\processor;

use Drupal\group\Entity\GroupContent;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntity;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * @SearchApiProcessor(
 *   id = "service_processor",
 *   label = @Translation("Service processor"),
 *   description = @Translation("Adds some service-related processing."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class ServiceProcessor extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    /** @var \Drupal\search_api\Plugin\search_api\datasource\ContentEntity $datasource */
    if ($datasource instanceof ContentEntity && $datasource->getEntityTypeId() == 'node') {
      $definition = [
        'label' => $this->t('HEL TPM group priority boost'),
        'description' => $this->t('Is the node boosted by the group'),
        'type' => 'boolean',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['hel_tpm_priority_boost'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $priority_item = FALSE;

    $entity = $item->getOriginalObject()->getValue();

    // Load the group content related to this entity.
    $group_content_array = GroupContent::loadByEntity($entity);

    if (!empty($group_content_array)) {
      /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
      foreach ($group_content_array as $group_content) {
        $group = $group_content->getGroup();
        if ($group) {
          if ($group->field_group_prioritise_in_search->value) {
            $priority_item = TRUE;
            break;
          }
        }
      }
    }

    $fields = $item->getFields(FALSE);
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($fields, 'entity:node', 'hel_tpm_priority_boost');
    foreach ($fields as $field) {
      $config = $field->getConfiguration();
      $field->addValue($priority_item);
    }
  }

}
