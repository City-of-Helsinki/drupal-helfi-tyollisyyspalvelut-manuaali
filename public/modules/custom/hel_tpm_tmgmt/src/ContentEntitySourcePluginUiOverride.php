<?php

namespace Drupal\hel_tpm_tmgmt;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_translation\ContentTranslationManager;
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
    'content_moderation_state',
  ];

  /**
   * The StackInterface instance for handling incoming requests.
   */
  private RequestStack $requestStack;

  /**
   * Service or utility handling moderation information retrieval.
   */
  private ModerationInformationInterface $moderationInfo;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = \Drupal::requestStack();
    $this->moderationInfo = \Drupal::service('content_moderation.moderation_information');
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

    if ($this->moderationInfo->isModeratedEntityType($entity_type) && $this->requestStack->getCurrentRequest()->query->has('type')) {
      $bundle = $this->requestStack->getCurrentRequest()->query->get('type');
      $search += $this->buildContentModerationFormElement($entity_type, $bundle);
    }
    return $form;
  }

  /**
   * Builds a form element for content moderation state selection.
   *
   * This method generates a select form element to choose a workflow state for
   * content moderation. If no workflow is defined for the given entity type and
   * bundle, an empty array is returned.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type object for which the moderation form element is built.
   * @param string $bundle
   *   The specific bundle of the entity type.
   *
   * @return array
   *   A render array containing the content moderation state select element, or
   *   an empty array if no workflow is defined.
   */
  protected function buildContentModerationFormElement(EntityTypeInterface $entity_type, $bundle) {
    $workflow = $this->moderationInfo->getWorkflowForEntityTypeAndBundle($entity_type->id(), $bundle);
    if (empty($workflow)) {
      return [];
    }
    $options = ['none' => $this->t('- None -')];
    $workflow_type_settings = $workflow->get('type_settings');
    foreach ($workflow_type_settings['states'] as $key => $state) {
      $options[$key] = $state['label'];
    }
    $element['content_moderation_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Workflow state'),
      '#options' => $options,
      '#default_value' => $this->requestStack->getCurrentRequest()->query->get('content_moderation_state') ?? 'none',
    ];
    return $element;
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
    if (!empty($property_conditions['content_moderation_state'])) {
      $content_moderation_state = $property_conditions['content_moderation_state'];
      unset($property_conditions['content_moderation_state']);
    }
    $query = parent::buildTranslatableEntitiesQuery($entity_type_id, $property_conditions);
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $id_key = $entity_type->getKey('id');
    if (!empty($property_conditions[$id_key])) {
      $search_id = (int) trim($property_conditions[$id_key]);
      $query->condition('e.' . $id_key, $search_id);
    }
    if (!empty($content_moderation_state)) {
      $table = 'content_moderation_state_field_data';
      if (!empty($property_conditions['type']) && ContentTranslationManager::isPendingRevisionSupportEnabled($entity_type_id, $property_conditions['type'])) {
        $table = 'content_moderation_state_field_revision';
        $ids = self::getContentWithModerationState($content_moderation_state);
        if (empty($ids)) {
          $query->condition('e.' . $id_key, 0);
        }
        else {
          $query->condition('e.' . $id_key, $ids, 'IN');
        }
      }
      $query->leftJoin($table, 'cm', 'cm.content_entity_id = e.nid AND cm.default_langcode = 1');

      $query->condition('cm.moderation_state', $content_moderation_state);
    }
    return $query;
  }

  /**
   * Retrieves a list of entity IDs based on the moderation state.
   *
   * This method queries the content moderation state table to find entity
   * IDs that match the specified moderation state. Only the latest revision
   * for each entity is considered in the results.
   *
   * @param string $moderation_state
   *   The moderation state to filter entities by.
   *
   * @return array
   *   An array of entity IDs that match the given moderation state.
   */
  private static function getContentWithModerationState($moderation_state) {
    $entity_ids = [];
    $database = \Drupal::database();

    $latest_revision_subquery = $database->select('content_moderation_state_field_revision', 'cm_latest');
    $latest_revision_subquery->addField('cm_latest', 'content_entity_id');
    $latest_revision_subquery->addExpression('MAX(cm_latest.content_entity_revision_id)', 'latest_revision_id');
    $latest_revision_subquery->condition('cm_latest.default_langcode', 1);
    $latest_revision_subquery->groupBy('cm_latest.content_entity_id');

    $query = $database->select('content_moderation_state_field_revision', 'cm');
    $query->innerJoin(
      $latest_revision_subquery,
      'latest',
      'latest.content_entity_id = cm.content_entity_id AND latest.latest_revision_id = cm.content_entity_revision_id'
    );
    $query->fields('cm', [
      'content_entity_id',
      'moderation_state',
      'content_entity_revision_id',
    ]);
    $query->condition('cm.default_langcode', 1);
    $result = $query->execute()->fetchAll();

    foreach ($result as $row) {
      if ($moderation_state !== $row->moderation_state) {
        continue;
      }
      $entity_ids[] = $row->content_entity_id;
    }
    return $entity_ids;
  }

}
