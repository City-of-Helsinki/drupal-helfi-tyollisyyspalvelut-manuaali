<?php

namespace Drupal\hel_tpm_tmgmt;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tmgmt_content\ContentEntitySourcePluginUi;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Extends ContentEntitySourcePluginUi to override and enhance functionality.
 *
 * This class modifies various aspects of the source plugin UI for content
 * entity-based translation workflows, including form handling and rendering.
 */
class ContentEntitySourcePluginUiOverride extends ContentEntitySourcePluginUi {

  /**
   * Whitelisted keys for the overview form base configuration.
   */
  protected const OVERVIEW_FORM_BASE_WHITELIST = [
    'langcode',
    'target_language',
    'target_status',
  ];

  /**
   * The StackInterface instance for handling incoming requests.
   */
  private RequestStack $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = \Drupal::requestStack();
  }

  /**
   * {@inheritDoc}
   */
  public function overviewSearchFormPart(array $form, FormStateInterface $form_state, $type) {
    $form = parent::overviewSearchFormPart($form, $form_state, $type);
    $entity_type = \Drupal::entityTypeManager()->getDefinition($type);
    $search = &$form['search_wrapper']['search'];
    $id_key = $entity_type->getKey('id');
    $search[$id_key] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID'),
      '#default_value' => $this->requestStack->getCurrentRequest()->query->get($id_key) ?? NULL,
      '#size' => 5,
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function overviewFormHeader($type) {
    return [
      'id' => ['data' => $this->t('ID')],
    ] + parent::overviewFormHeader($type);
  }

  /**
   * {@inheritDoc}
   */
  public function overviewForm(array $form, FormStateInterface $form_state, $type) {
    $form += $this->overviewSearchFormPart($form, $form_state, $type);

    $form['#attached']['library'][] = 'tmgmt/admin';

    $form['items'] = [
      '#type' => 'tableselect',
      '#header' => $this->overviewFormHeader($type),
      '#empty' => $this->t('No source items matching given criteria have been found.'),
    ];

    // Build a list of allowed search conditions and get
    // their values from the request.
    $entity_type = \Drupal::entityTypeManager()->getDefinition($type);
    $whitelist = $this->getOverviewFormWhitelist($entity_type);

    $search_property_params = array_filter(\Drupal::request()->query->all());
    $search_property_params = array_intersect_key($search_property_params, array_flip($whitelist));
    $bundles = $this->getTranslatableBundles($type);

    foreach (static::getTranslatableEntities($type, $search_property_params, TRUE) as $entity) {
      // This occurs on user entity type.
      if ($entity->id()) {
        $form['items']['#options'][$entity->id()] = $this->overviewRow($entity, $bundles);
      }
    }

    $form['pager'] = ['#type' => 'pager'];

    return $form;
  }

  /**
   * Retrieves the whitelist of fields for the overview form.
   *
   * This method generates a list of allowed fields based on a predefined
   * base whitelist and additional keys provided by the entity type definition.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition being handled.
   *
   * @return array
   *   An array of field keys allowed in the overview form.
   */
  protected function getOverviewFormWhitelist(EntityTypeInterface $entity_type) : array {
    $whitelist = self::OVERVIEW_FORM_BASE_WHITELIST;
    $entity_type_keys = ['bundle', 'label', 'id'];
    foreach ($entity_type_keys as $key) {
      if (!$entity_type->hasKey($key)) {
        continue;
      }
      $whitelist[] = $entity_type->getKey($key);
    }
    return $whitelist;
  }

  /**
   * {@inheritdoc}
   */
  public static function buildTranslatableEntitiesQuery($entity_type_id, $property_conditions = []) {
    $query = parent::buildTranslatableEntitiesQuery($entity_type_id, $property_conditions);
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $id_key = $entity_type->getKey('id');
    if (!empty($property_conditions[$id_key])) {
      $search_id = (int) trim($property_conditions[$id_key]);
      $query->condition('e.' . $id_key, $search_id);
    }
    return $query;
  }

}
