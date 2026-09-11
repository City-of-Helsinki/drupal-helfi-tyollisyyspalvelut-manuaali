<?php

namespace Drupal\field_description_tooltip\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure the field description settings to convert into a tooltiop.
 */
class FieldDescriptionTooltipConfigForm extends ConfigFormBase implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The entity definition update manager.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $instance->entityDefinitionUpdateManager = $container->get('entity.definition_update_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'field_description_tooltip_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'field_description_tooltip.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Load the saved configuration (if available).
    $config = $this->config('field_description_tooltip.settings');
    // Get all the available installed entity types.
    $entity_types = $this->entityDefinitionUpdateManager->getEntityTypes();
    $fieldable_interface = 'Drupal\Core\Entity\FieldableEntityInterface';

    $form['info'] = [
      '#markup' => '<p>' . $this->t('Set which entity form fields will convert the description into a tooltip.')
      . '</p><strong>' . $this->t('NOTE:') . ' </strong>'
      . $this->t('The description tooltip is only workable for the edit/create entity forms.') . '</p>',
    ];

    $form['tooltip_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Tolltip all?'),
      '#default_value' => $config->get('tooltip_all') ?? FALSE,
      '#description' => $this->t('Convert all entity field descriptions into a tooltip or not.'),
    ];

    $form['tooltip_position'] = [
      '#type' => 'details',
      '#title' => $this->t('Popup position'),
      '#description' => $this->t(
        'Set the tooltip popup position related to the field. Check @this page for more information.',
        [
          '@this' => Link::fromTextAndUrl(
            $this->t('this'),
            Url::fromUri('https://jqueryui.com/position/')
          )->toString(),
        ]
      ),
      '#open' => TRUE,
    ];

    $position_1 = [
      'left' => 'left',
      'center' => 'center',
      'right' => 'right',
    ];
    $position_2 = [
      'top' => 'top',
      'center' => 'center',
      'bottom' => 'bottom',
    ];
    $form['tooltip_position']['my_1'] = [
      '#type' => 'select',
      '#title' => 'my',
      '#options' => $position_1,
      '#default_value' => $config->get('my_1') ?? 'left',
    ];

    $form['tooltip_position']['my_2'] = [
      '#type' => 'select',
      '#options' => $position_2,
      '#default_value' => $config->get('my_2') ?? 'bottom',
    ];

    $form['tooltip_position']['at_1'] = [
      '#type' => 'select',
      '#title' => 'at',
      '#options' => $position_1,
      '#default_value' => $config->get('my_1') ?? 'left',
    ];

    $form['tooltip_position']['at_2'] = [
      '#type' => 'select',
      '#options' => $position_2,
      '#default_value' => $config->get('my_2') ?? 'bottom',
    ];

    foreach ($entity_types as $type) {
      // Do not show the non-fieldable entity types.
      if (!$type->getOriginalClass() || !in_array($fieldable_interface, class_implements($type->getOriginalClass()))) {
        continue;
      }
      $type_id = $type->id();

      // Get the entity type bundles.
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($type_id);

      if (!$bundles) {
        continue;
      }
      $form[$type_id] = [
        '#type' => 'details',
        '#title' => $this->t('Entity type: %type', ['%type' => $type_id]),
        '#open' => TRUE,
      ];

      foreach ($bundles as $bundle => $label) {
        $form[$type_id][$bundle] = [
          '#type' => 'details',
          '#title' => $this->t('Bundle: %bundle', ['%bundle' => $label['label']]),
          '#open' => FALSE,
        ];

        // Get all the fields from the entity bundle.
        $fields = $this->entityFieldManager->getFieldDefinitions($type_id, $bundle);

        $count = $active = 0;
        foreach ($fields as $field) {
          // Do not count the read-only or no form options listed fields.
          if ($field->isReadOnly() || !$field->getDisplayOptions('form')) {
            continue;
          }
          $count++;

          // Generate the field name based on the params.
          $field_name = $type_id . ':' . $bundle . ':' . $field->getName();
          $form[$type_id][$bundle][$field_name] = [
            '#type' => 'checkbox',
            '#title' => $field->getLabel(),
            '#default_value' => $config->get($field_name) ?? FALSE,
          ];

          if (!empty($config->get($field_name))) {
            $active++;
          }
        }

        if (!$count) {
          $form[$type_id][$bundle][] = [
            '#markup' => $this->t('No available fields'),
          ];
        }

        // Make the details element open once there are any selected field.
        if ($active) {
          $form[$type_id][$bundle]['#open'] = TRUE;
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Clean the form values.
    $form_state->cleanValues();

    // Retrieve the configuration prepared to be saved.
    $settings = $this->configFactory->getEditable('field_description_tooltip.settings');
    foreach ($form_state->getValues() as $name => $config) {
      $settings->set($name, $config);
    }
    $settings->save();

    parent::submitForm($form, $form_state);
  }

}
