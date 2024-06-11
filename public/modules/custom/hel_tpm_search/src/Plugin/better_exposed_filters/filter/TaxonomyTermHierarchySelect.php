<?php

namespace Drupal\hel_tpm_search\Plugin\better_exposed_filters\filter;

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


}
