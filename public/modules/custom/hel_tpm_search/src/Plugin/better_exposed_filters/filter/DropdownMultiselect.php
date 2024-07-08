<?php

namespace Drupal\hel_tpm_search\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\selective_better_exposed_filters\Plugin\better_exposed_filters\filter\SelectiveFilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default widget implementation.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "bef_dropdown_multiselet",
 *   label = @Translation("Multiselect Dropdown"),
 * )
 */
class DropdownMultiselect extends FilterWidgetBase implements ContainerFactoryPluginInterface {

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

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $languageManager) {
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
   * Add multiselect support for dropdown filter.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return void
   *   -
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

    if ($this->configuration['term_optgroup']) {
      $this->createOptGroups($form[$field_id]);
    }

    $form[$field_id]['#attributes']['class'][] = 'dropdownMultiselect';

    $form['#attached']['library'][] = 'hel_tpm_search/dropdown_multiselect';

    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    SelectiveFilterBase::exposedFormAlter($this->view, $filter, $this->configuration, $form, $form_state);
  }

  /**
   * Create optgroup from taxonomy terms.
   *
   * @param array $field
   *   Select field.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function createOptGroups(array &$field) {
    if ($field['#type'] !== 'select') {
      return;
    }
    $optgroup = [];
    $options = $field['#options'];
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple(array_keys($options));
    $language = $this->languageManager->getCurrentLanguage()->getId();
    foreach ($terms as $term) {

      // Load term in currnet language.
      if ($term->hasTranslation($language)) {
        $term = $term->getTranslation($language);
      }
      // Get parent
      $parent = $term->parent->entity;
      if (empty($parent)) {
        continue;
      }
      // Check if parent has translation and load if there is one.
      if ($parent->hasTranslation($language)) {
        $parent = $parent->getTranslation($language);
      }

      $optgroup[$parent->label()][$term->id()] = $term->label();
    }
    // Remove empty parents.
    $optgroup = array_filter($optgroup);
    $field['#options'] = $optgroup;
  }

}
