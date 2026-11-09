<?php

namespace Drupal\hel_tpm_dropdown_filter\Plugin\better_exposed_filters\filter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;
use Drupal\selective_better_exposed_filters\Plugin\better_exposed_filters\filter\SelectiveFilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Renders an exposed filter as checkboxes inside a dropdown.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "hel_tpm_dropdown_filter",
 *   label = @Translation("Dropdown checkboxes"),
 * )
 */
class DropdownFilter extends FilterWidgetBase implements ContainerFactoryPluginInterface, TrustedCallbackInterface {

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
    protected Request $request,
    protected ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $request, $configFactory);
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $configuration = parent::defaultConfiguration() + SelectiveFilterBase::defaultConfiguration();
    $configuration['term_optgroup'] = FALSE;
    $configuration['single_select'] = FALSE;
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    $form = parent::buildConfigurationForm($form, $form_state);
    $form += SelectiveFilterBase::buildConfigurationForm($filter, $this->configuration);
    $form['term_optgroup'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Render terms in optgroup'),
      '#default_value' => !empty($this->configuration['term_optgroup']),
    ];
    $form['single_select'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow selecting only one option at a time'),
      '#default_value' => !empty($this->configuration['single_select']),
    ];
    return $form;
  }

  /**
   * Renders the filter as checkboxes inside a dropdown.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return void
   *   -
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    // Form element is designated by the element ID which is user-
    // configurable.
    $field_id = $filter->options['is_grouped'] ? $filter->options['group_info']['identifier'] :
      $filter->options['expose']['identifier'];

    parent::exposedFormAlter($form, $form_state);
    SelectiveFilterBase::exposedFormAlter($this->view, $filter, $this->configuration, $form, $form_state);

    if (empty($form[$field_id]['#options'])) {
      return;
    }
    $element = &$form[$field_id];
    if ($element['#type'] === 'select' && empty($element['#multiple'])) {
      return;
    }

    $options = $this->flattenOptions($element['#options']);
    $groups = [];
    if ($this->configuration['term_optgroup']) {
      $groups = $this->getTermGroups(array_keys($options));
      // Only child terms are selectable, ordered by group.
      $grouped = array_fill_keys(array_merge(...array_values($groups)), NULL);
      $options = array_intersect_key(array_replace($grouped, $options), $grouped);
    }

    $this->normalizeUserInput($form_state, $field_id);

    $element['#type'] = 'checkboxes';
    $element['#options'] = $options;
    unset($element['#multiple'], $element['#size']);
    $element['#theme'] = 'hel_tpm_dropdown_filter';
    $element['#pre_render'][] = [static::class, 'preRenderDropdown'];
    $element['#element_validate'][] = [static::class, 'validateDropdown'];
    $element['#dropdown_groups'] = $groups;
    $element['#dropdown_single'] = !empty($this->configuration['single_select']);

    $form['#attached']['library'][] = 'hel_tpm_dropdown_filter/dropdown_filter';
  }

  /**
   * Pre-render callback for the dropdown filter.
   *
   * @param array $element
   *   The checkboxes element.
   *
   * @return array
   *   The element.
   */
  public static function preRenderDropdown(array $element): array {
    // The template renders the label and description, so remove the fieldset
    // that checkboxes elements are wrapped in.
    $element['#theme_wrappers'] = [];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRenderDropdown'];
  }

  /**
   * Removes unchecked options from the submitted value.
   *
   * Form API submits unchecked checkboxes as option => 0. Filters expect
   * only the selected values, like the select element this replaces.
   *
   * @param array $element
   *   The checkboxes element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateDropdown(array &$element, FormStateInterface $form_state): void {
    $value = $form_state->getValue($element['#parents']);
    if (is_array($value)) {
      $checked = Checkboxes::getCheckedCheckboxes($value);
      $form_state->setValueForElement($element, array_intersect_key($value, array_flip($checked)));
    }
  }

  /**
   * Converts the submitted value of the filter to a list of options.
   *
   * Checkboxes expect an array and fail on other input, so a URL like
   * ?field=value would otherwise cause an error.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_id
   *   The filter identifier.
   */
  private function normalizeUserInput(FormStateInterface $form_state, string $field_id): void {
    $input = $form_state->getUserInput();
    if (!array_key_exists($field_id, $input)) {
      return;
    }
    $value = is_array($input[$field_id]) ? $input[$field_id] : [$input[$field_id]];
    $value = array_filter($value, fn ($item) => is_string($item) && $item !== '');
    if ($value) {
      $input[$field_id] = $value;
    }
    else {
      unset($input[$field_id]);
    }
    $form_state->setUserInput($input);
  }

  /**
   * Flattens optgroups and converts option labels to strings.
   *
   * @param array $options
   *   Select options.
   *
   * @return array
   *   Option labels keyed by option value.
   */
  private function flattenOptions(array $options): array {
    $flat = [];
    foreach ($options as $key => $option) {
      if (is_array($option)) {
        $flat += $this->flattenOptions($option);
      }
      elseif (is_object($option) && isset($option->option)) {
        $flat += array_map('strval', $option->option);
      }
      else {
        $flat[$key] = (string) $option;
      }
    }
    return $flat;
  }

  /**
   * Groups taxonomy terms by their parent term.
   *
   * @param array $tids
   *   Term IDs.
   *
   * @return array
   *   Term IDs keyed by translated parent term label, in term order.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getTermGroups(array $tids): array {
    $groups = [];
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tids);
    // Add parents first so that the parent term order is preserved.
    foreach ($terms as $term) {
      /** @var \Drupal\taxonomy\Entity\Term $term */
      if (empty($term->parent->entity)) {
        $groups[$this->getTranslatedLabel($term)] = [];
      }
    }
    // Add terms to parents preserving the term order.
    foreach ($terms as $term) {
      /** @var \Drupal\taxonomy\Entity\Term $term */
      $parent = $term->parent->entity;
      if (!empty($parent)) {
        $groups[$this->getTranslatedLabel($parent)][] = $term->id();
      }
    }
    // Remove empty parents.
    return array_filter($groups);
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
