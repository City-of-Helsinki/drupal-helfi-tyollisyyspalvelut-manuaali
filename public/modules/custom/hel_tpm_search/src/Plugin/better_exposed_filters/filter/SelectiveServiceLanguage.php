<?php

namespace Drupal\hel_tpm_search\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Default widget implementation.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "selective_service_language_multiselect",
 *   label = @Translation("Selective service language multiselect"),
 * )
 */
class SelectiveServiceLanguage extends FilterWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + SelectiveLanguageBase::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    $form = parent::buildConfigurationForm($form, $form_state);
    $form += SelectiveLanguageBase::buildConfigurationForm($filter, $this->configuration);
    return $form;
  }

  /**
   * {@inheritdoc}
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

    $form[$field_id]['#attributes']['class'][] = 'dropdownMultiselect';
    $form['#attached']['library'][] = 'hel_tpm_search/dropdown_multiselect';

    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    SelectiveLanguageBase::exposedFormAlter($this->view, $filter, $this->configuration, $form, $form_state);
  }

}
