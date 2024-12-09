<?php

namespace Drupal\hel_tpm_search_autosuggest\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a Hel TPM Search Autosuggest form.
 */
class SearchAutoSuggestForm extends FormBase implements ContainerInjectionInterface {

  /**
   * Langauge manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private LanguageManagerInterface $languageManager;

  /**
   * Request stack service.
   */
  private RequestStack $request;

  /**
   * Constructior.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager interface.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   Current request.
   */
  public function __construct(LanguageManagerInterface $language_manager, Request $current_request) {
    $this->languageManager = $language_manager;
    $this->currentRequest = $current_request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

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
    $language = $this->languageManager->getCurrentLanguage()->getId();
    $form['#action'] = sprintf('/%s/search', $language);
    $form['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#id' => 'hel_tpm_search_form',
      '#attributes' => [
        'placeholder' => $this->t('Search from all services'),
        'autocomplete' => 'off',
      ],
      '#required' => TRUE,
      '#default_value' => $this->currentRequest->query->get('search_api_fulltext'),
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
