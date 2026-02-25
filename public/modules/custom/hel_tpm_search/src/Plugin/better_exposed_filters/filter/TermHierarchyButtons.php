<?php

namespace Drupal\hel_tpm_search\Plugin\better_exposed_filters\filter;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;
use Drupal\selective_better_exposed_filters\Plugin\better_exposed_filters\filter\SelectiveFilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default widget implementation.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "bef_term_hierarchy_buttons",
 *   label = @Translation("Term Hierarchy buttons"),
 * )
 */
class TermHierarchyButtons extends FilterWidgetBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
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
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration() + SelectiveFilterBase::defaultConfiguration();
    $configuration['term_optgroup'] = FALSE;
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    $form = parent::buildConfigurationForm($form, $form_state);
    $form += SelectiveFilterBase::buildConfigurationForm($filter, $this->configuration);
    $form['term_optgroup'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Render terms in optgroup'),
      '#default_value' => !empty($this->configuration['term_optgroup']),
    ];
    return $form;
  }

  /**
   * Alters the exposed form to adjust form elements and enhance functionality.
   *
   * @param array $form
   *   The renderable form array that is being altered.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return void
   *   This method does not return any value.
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    // Form element is designated by the element ID which is user-
    // configurable.
    $field_id = $filter->options['is_grouped'] ? $filter->options['group_info']['identifier'] :
      $filter->options['expose']['identifier'];

    parent::exposedFormAlter($form, $form_state);

    if (!empty($form[$field_id]['#options']) && $form[$field_id]['#type'] != 'select') {
      $form[$field_id]['#type'] = 'select';
      $form[$field_id]['#multiple'] = TRUE;
    }

    $form[$field_id]['#attributes']['class'][] = 'hidden';

    $form[$field_id . '_button_select'] = [
      '#type' => 'container',
      '#attributes' => [
        'parent_field' => $form[$field_id]['#multiple'] === TRUE ? sprintf('%s[]', $field_id) : $field_id,
        'id' => sprintf('%s_select_buttons', $field_id),
        'class' => ['term-hierarchy-buttons'],
      ],
      'buttons' => $this->createHierarchyButtons($form[$field_id], $field_id),
    ];

    $form[$field_id]['#attributes']['class'][] = 'term-hierarchy-buttons-select';

    $form['#attached']['library'][] = 'hel_tpm_search/term_hierarchy_buttons';

    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    SelectiveFilterBase::exposedFormAlter($this->view, $filter, $this->configuration, $form, $form_state);
  }

  /**
   * Creates a hierarchy of buttons based on a given field's options.
   *
   * Processes a field's options structured as taxonomy terms and organizes
   * them hierarchically, associating parent and child relationships into
   * a rendered button group.
   *
   * @param array $field
   *   A reference to the field array structure
   *   containing the hierarchy options.
   *   The field must have a '#type' of 'select' and be structured accordingly.
   * @param string|int $field_id
   *   A unique identifier for the field that is used to assign attributes to
   *   the created buttons.
   *
   * @return array
   *   An array representing the hierarchy of buttons, or NULL if the field
   *   is not of type 'select'.
   */
  private function createHierarchyButtons(array &$field, $field_id) : array {
    if ($field['#type'] !== 'select') {
      return [];
    }
    $element = [
      'parent_tids' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['term-hierarchy-button-group', 'term-hierarchy-parent-group'],
        ],
      ],
    ];
    $optionTids = [];
    $btnGroup = [];
    $options = $field['#options'];

    foreach ($options as $option) {
      $tid = array_keys($option->option);
      $optionTids[$tid[0]] = $tid[0];
    }

    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple(array_keys($optionTids));
    // Add parents first so that the parent term order is preserved.
    foreach ($terms as $term) {
      /** @var \Drupal\taxonomy\Entity\Term $term */
      if (empty($term->parent->entity)) {
        $btnGroup[$term->id()] = [
          'label' => $this->getTranslatedLabel($term),
        ];
      }
    }
    // Add terms to parents preserving the term order.
    foreach ($terms as $term) {
      /** @var \Drupal\taxonomy\Entity\Term $term */
      $parent = $term->parent->entity;
      if (!empty($parent)) {
        $btnGroup[$term->id()] = [
          'label' => $this->getTranslatedLabel($term),
          'parent_id' => $parent->id(),
        ];
      }
    }
    foreach ($btnGroup as $tid => $row) {
      if (empty($row['parent_id'])) {
        $element['parent_tids'][$tid] = $this->createButtonElement($tid, $field_id, $row);
        continue;
      }

      $parent_container = 'parent-group-' . $row['parent_id'];
      if (empty($element[$parent_container])) {
        $element[$parent_container] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'term-hierarchy-button-group',
              'term-hierarchy-child-group',
              'hidden',
            ],
            'data-group-parent-tid' => $row['parent_id'],
          ],
        ];
      }
      $element[$parent_container][$tid] = $this->createButtonElement($tid, $field_id, $row);
    }

    return $element;
  }

  /**
   * Creates a button element for a hierarchical selection interface.
   *
   * @param int|string $term_id
   *   The term ID associated with the button element.
   * @param string $field_id
   *   The field ID the button is linked to.
   * @param array $row
   *   The data array for the current term, which includes properties like
   *   'label' and optionally 'parent_id'.
   *
   * @return array
   *   A renderable array representing the button element.
   */
  private function createButtonElement($term_id, $field_id, $row) {
    $element = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $row['label'],
      '#attributes' => [
        'id' => sprintf('btn-%s-%s', $field_id, $term_id),
        'class' => ['hierarchy-select-button', 'hierarchy-child-button'],
        'data-term-id' => $term_id,
      ],
    ];
    if (!empty($row['parent_id'])) {
      $element['#attributes']['data-parent-tid'] = $row['parent_id'];
      $element['#attributes']['class'][] = 'child-button';
    }
    return $element;
  }

  /**
   * Get translated label if it exists and original label otherwise.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *   The entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|mixed|string|null
   *   The label.
   */
  private function getTranslatedLabel(ContentEntityBase $entity) {
    $language = $this->languageManager->getCurrentLanguage()->getId();
    if ($entity->hasTranslation($language)) {
      $entity = $entity->getTranslation($language);
    }
    return $entity->label();
  }

}
