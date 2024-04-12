<?php
namespace Drupal\hel_tpm_general\Plugin\FieldTypeExport;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\entity_export_csv\Plugin\FieldTypeExportBase;

/**
 * Defines paragraph field exporter.
 *
 * @FieldTypeExport(
 *    id = "paragraph_export",
 *    label = @Translation("Paragraph export"),
 *    description = @Translation("Paragraph export"),
 *    weight = 0,
 *    field_type = {
 *      "entity_reference_revisions",
 *    },
 *    entity_type = {},
 *    bundle = {},
 *    field_name = {},
 *    exclusive = FALSE,
 *  )
 */
final class ParagraphExport extends FieldTypeExportBase {
  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      'message' => [
        '#markup' => $this->t('Paragraph field type exporter.'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function massageExportPropertyValue(FieldItemInterface $field_item, $property_name, FieldDefinitionInterface $field_definition, $options = []) {
    if ($field_item->isEmpty()) {
      return NULL;
    }
    $configuration = $this->getConfiguration();
    if (empty($configuration['format'])) {
      return $field_item->get($property_name)->getValue();
    }

    $format = $configuration['format'];
    if ($format === 'paragraph_fields') {
      $entity = $field_item->get('entity')->getValue();

      if (!$entity instanceof EntityInterface) {
        return $field_item->get($property_name)->getValue();
      }
      return $this->renderFields($entity);
    }

    return $field_item->get($property_name)->getValue();
  }

  /**
   * Render
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return string
   */
  private function renderFields(EntityInterface $entity) {
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $view_builder = $entity_type_manager->getViewBuilder($entity->getEntityTypeId());
    $view = $view_builder->view($entity, 'full');
    $render = \Drupal::service('renderer')->render($view);

    return $this->cleanString($render);
  }

  private function cleanString(string $string) {
    $string = strip_tags($string);
    $str_arr = explode(PHP_EOL, $string);
    $str_arr = array_filter($str_arr, function($v) {
      return !empty(preg_replace("/\s+/", "", $v));
    });
    $str_arr = array_map('trim', $str_arr);

    return implode(PHP_EOL, $str_arr);
  }

  /**
   * {@inheritdoc}
   */
  protected function getFormatExportOptions(FieldDefinitionInterface $field_definition) {
    $options = parent::getFormatExportOptions($field_definition);
    $options['paragraph_fields'] = $this->t('Paragraph fields');
    return $options;
  }


}