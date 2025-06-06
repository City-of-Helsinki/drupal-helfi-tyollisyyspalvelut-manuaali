<?php

namespace Drupal\hel_tpm_general\Plugin\FieldTypeExport;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\entity_export_csv\Plugin\FieldTypeExportBase;
use Drupal\field\Entity\FieldConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The renderer interface.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private $renderer;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter,
    ModuleHandlerInterface $module_handler,
    EntityRepositoryInterface $entity_repository,
    EntityFieldManagerInterface $entity_field_manager,
    LanguageManagerInterface $language_manager,
    ConfigFactoryInterface $config_factory,
    RendererInterface $renderer,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager,
      $date_formatter,
      $module_handler,
      $entity_repository,
      $entity_field_manager,
      $language_manager,
      $config_factory
    );
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('module_handler'),
      $container->get('entity.repository'),
      $container->get('entity_field.manager'),
      $container->get('language_manager'),
      $container->get('config.factory'),
      $container->get('renderer')
    );
  }

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
  public function massageExportPropertyValue(
    FieldItemInterface $field_item,
    $property_name,
    FieldDefinitionInterface $field_definition,
    $options = [],
  ) {
    if ($field_item->isEmpty()) {
      return NULL;
    }
    $configuration = $this->getConfiguration();

    $format = $configuration['format'];
    if (empty($format) || $format === 'paragraph_fields') {
      $entity = $field_item->get('entity')->getValue();

      if (!$entity instanceof EntityInterface) {
        return $field_item->get($property_name)->getValue();
      }
      return $this->renderFields($entity);
    }

    if ($format === 'paragraph_fields_separated') {
      $entity = $field_item->get('entity')->getValue();
      return $this->renderField($entity, $property_name);
    }

    return $field_item->get($property_name)->getValue();
  }

  /**
   * {@inheritdoc}
   */
  protected function propertiesInSeparateColumns() {
    if ($this->configuration['format'] !== 'paragraph_fields_separated') {
      return parent::propertiesInSeparateColumns();
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPropertiesSelected(FieldDefinitionInterface $field_definition) {
    // @todo Change the autogenerated stub.
    $properties_selected = parent::getPropertiesSelected($field_definition);
    if ($this->getConfiguration()['format'] === 'paragraph_fields_separated') {
      $properties_selected = [];
      $handler_settings = $field_definition->getSetting('handler_settings');
      $field_definitions = $this->entityFieldManager->getFieldDefinitions(
        'paragraph',
        array_key_first($handler_settings['target_bundles'])
      );
      foreach ($field_definitions as $field_key => $value) {
        if (!$value instanceof FieldConfig) {
          continue;
        }
        $properties_selected[$field_key] = $field_key;
      }
    }
    return $properties_selected;
  }

  /**
   * Render entity fields.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which is rendered.
   *
   * @return string
   *   Rendered entity.
   *
   * @throws \Exception
   */
  private function renderFields(EntityInterface $entity) {
    $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
    $view = $view_builder->view($entity, 'full');
    $render = $this->renderer->render($view);
    return $this->cleanString($render);
  }

  /**
   * Render single field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   * @param string $field
   *   Field key.
   * @param bool $show_label
   *   Boolean whether to render label or not.
   *
   * @return string
   *   Cleaned rendered field value.
   *
   * @throws \Exception
   */
  private function renderField(EntityInterface $entity, $field, $show_label = FALSE) {
    $field = $entity->get($field)->view();
    if (empty($field['#theme'])) {
      return '';
    }
    if ($show_label === FALSE) {
      $field['#label_display'] = 'hidden';
    }
    return $this->cleanString($this->renderer->render($field));
  }

  /**
   * Remove html and excess whitespace from string.
   *
   * @param string $string
   *   String to clean.
   *
   * @return string
   *   Cleaned string.
   */
  private function cleanString(string $string) {
    $string = strip_tags($string);
    $str_arr = explode(PHP_EOL, $string);
    $str_arr = array_filter($str_arr, function ($v) {
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
    $options['paragraph_fields_separated'] = $this->t('Paragraph fields in separated columns');
    return $options;
  }

}
