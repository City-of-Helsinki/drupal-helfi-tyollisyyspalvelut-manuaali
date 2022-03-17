<?php

namespace Drupal\hel_tpm_url_shortener\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a Hel TPM Url shortener form.
 */
class ShortenUrlLinkForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hel_tpm_url_shortener_shorten_url_link';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'shorten-link']
    ];
    $form['wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Create Link'),
      '#attributes' => ['tabindex' => 3],
      '#ajax' => [
        'callback' => '::submitAjaxCall',
        'wrapper' => 'shorten-link',
        'event' => 'click',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    return $form['wrapper'];
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return mixed
   */
  public function submitAjaxCall(array &$form, FormStateInterface $form_state) {
    $short_link_generator = \Drupal::service('hel_tpm_url_shortener.short_url_service');

    $url = $short_link_generator->generateShortLink();

    if (empty($url)) {
      return $form['wrapper'];
    }

    $form['wrapper']['submit']['#access'] = FALSE;
    $form['wrapper']['link']['#markup'] = $url->getShortUrl();

    return $form['wrapper'];
  }

}
