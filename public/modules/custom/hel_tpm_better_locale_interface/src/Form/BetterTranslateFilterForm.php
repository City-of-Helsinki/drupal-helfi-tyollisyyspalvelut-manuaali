<?php

namespace Drupal\hel_tpm_better_locale_interface\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\locale\Form\TranslateFormBase;

/**
 * Provides an enhanced translation filter form with "All" language option.
 */
class BetterTranslateFilterForm extends TranslateFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hel_tpm_better_translate_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $filters = $this->translateFilters();
    $filter_values = $this->translateFilterValues();

    $form['#attached']['library'][] = 'locale/drupal.locale.admin';

    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filter translatable strings'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['clearfix']],
    ];
    foreach ($filters as $key => $filter) {
      // Special case for 'string' filter.
      if ($key == 'string') {
        $form['filters']['status']['string'] = [
          '#type' => 'search',
          '#title' => $filter['title'],
          '#description' => $filter['description'],
          '#default_value' => $filter_values[$key],
        ];
      }
      else {
        $empty_option = $filter['options'][$filter['default']] ?? $this->t('All');
        $form['filters']['status'][$key] = [
          '#title' => $filter['title'],
          '#type' => 'select',
          '#empty_value' => $filter['default'],
          '#empty_option' => $empty_option,
          '#size' => 0,
          '#options' => $filter['options'],
          '#default_value' => $filter_values[$key],
        ];
        if (isset($filter['states'])) {
          $form['filters']['status'][$key]['#states'] = $filter['states'];
        }
      }
    }

    $form['filters']['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['container-inline']],
    ];
    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];
    if ($this->getRequest()->getSession()->has('locale_translate_filter')) {
      $form['filters']['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#submit' => ['::resetForm'],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function translateFilters() {
    $filters = parent::translateFilters();
    $filters['langcode']['default'] = 'all';
    $filters['langcode']['options'] = ['all' => $this->t('All')] + $filters['langcode']['options'];
    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $filters = $this->translateFilters();
    $session_filters = $this->getRequest()->getSession()->get('locale_translate_filter', []);
    foreach ($filters as $name => $filter) {
      if ($form_state->hasValue($name)) {
        $session_filters[$name] = trim($form_state->getValue($name));
      }
    }
    $this->getRequest()->getSession()->set('locale_translate_filter', $session_filters);
    $form_state->setRedirect('locale.translate_page');
  }

  /**
   * Provides a submit handler for the reset button.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $this->getRequest()->getSession()->remove('locale_translate_filter');
    $form_state->setRedirect('locale.translate_page');
  }

}
