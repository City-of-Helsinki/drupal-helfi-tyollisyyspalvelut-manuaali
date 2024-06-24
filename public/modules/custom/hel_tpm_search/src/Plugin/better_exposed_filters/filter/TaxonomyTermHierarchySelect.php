<?php

namespace Drupal\hel_tpm_search\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\BetterExposedFiltersHelper;
use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;
use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\RadioButtons;
use Drupal\Core\Form\FormStateInterface;
use Drupal\selective_better_exposed_filters\Plugin\better_exposed_filters\filter\SelectiveFilterBase;

/**
 * Default widget implementation.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "bef_taxonomy_term_hierarchy_select",
 *   label = @Translation("Taxonomy term hieararchy select"),
 * )
 */
class TaxonomyTermHierarchySelect extends RadioButtons {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + SelectiveFilterBase::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    $form = parent::buildConfigurationForm($form, $form_state);
    $form += SelectiveFilterBase::buildConfigurationForm($filter, $this->configuration);
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

    $form[$field_id]['#attributes']['class'][] = 'hierarchy-select-buttons';
    $form['#attached']['library'][] = 'hel_tpm_search/taxonomy_hierarchy_select';
  }

  /**
   * Override FilterWidgetBase:processSortedOptions
   * to allow taxonomies be sorted by hand.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   The altered element.
   */
  public function processSortedOptions(array $element, FormStateInterface $form_state) {
    $options = &$element['#options'];

    // Ensure "- Any -" value does not get sorted.
    $any_option = FALSE;
    if (empty($element['#required']) && $element['#required'] !== FALSE) {
      // We use array_slice to preserve they keys needed to determine the value
      // when using a filter (e.g. taxonomy terms).
      $any_option = array_slice($options, 0, 1, TRUE);
      // Array_slice does not modify the existing array, we need to remove the
      // option manually.
      unset($options[key($any_option)]);
    }

    // Not all option arrays will have simple data types. We perform a custom
    // sort in case users want to sort more complex fields (e.g taxonomy terms).
    // Skip sorting nested fields because it interferes with taxonomy sort.
    if (empty($element['#nested'])) {
      $options = BetterExposedFiltersHelper::sortOptions($options);
    }

    // Restore the "- Any -" value at the first position.
    if ($any_option) {
      $options = $any_option + $options;
    }

    return $element;
  }

}
