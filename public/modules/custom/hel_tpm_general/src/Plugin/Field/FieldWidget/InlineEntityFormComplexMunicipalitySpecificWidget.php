<?php

namespace Drupal\hel_tpm_general\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormComplex;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the 'hel_tpm_general_inline_entity_form_complex_improved' widget.
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
class InlineEntityFormComplexMunicipalitySpecificWidget extends InlineEntityFormComplex {

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository, ModuleHandlerInterface $module_handler, SelectionPluginManagerInterface $selection_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $entity_type_bundle_info, $entity_type_manager, $entity_display_repository, $module_handler, $selection_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('module_handler'),
      $container->get('plugin.manager.entity_reference_selection')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // @todo Change the autogenerated stub.
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $this->relabelEntities($element['entities']);
    return $element;
  }

  /**
   * Helper method to relable widget entities.
   *
   * @param array $entities
   *   Array of entities.
   */
  protected function relabelEntities(array &$entities) {
    foreach ($entities as $key => &$row) {
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
