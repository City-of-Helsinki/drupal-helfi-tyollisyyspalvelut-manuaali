<?php

namespace Drupal\hel_tpm_search_autosuggest\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a Hel TPM Search Autosuggest form.
 */
class SearchAutoSuggestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hel_tpm_search_autosuggest_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $form['#action'] = sprintf('/%s/search', $language);
    $form['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#id' => 'hel_tpm_search_form',
      '#attributes' => [
        'placeholder' => t('Search from all services'),
        'autocomplete' => 'off',
      ],
      '#required' => TRUE,
      '#default_value' => \Drupal::request()->query->get('search_api_fulltext'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#title' => $this->t('Search from all services'),
    ];

    $form['list_items'] = [
      '#theme' => 'hel_tpm_search_autocomplete',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('search'))) {
      $form_state->setErrorByName('search', $this->t("This can't be empty"));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $route_name = Url::fromUserInput($form['#action'])->getRouteName();
    $form_state->setRedirect($route_name, ['search_api_fulltext' => $form_state->getValue('search')]);
  }

}
