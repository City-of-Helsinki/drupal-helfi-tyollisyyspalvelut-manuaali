<?php

namespace Drupal\hel_tpm_url_shortener\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

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
      '#attributes' => ['id' => 'shorten-link'],
    ];

    $element['current_path'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['current-path']],
      '#attached' => [
        'library' => [
          'hel_tpm_url_shortener/url_shortener'
        ]
      ],
    ];
    $element['submit'] = [
      '#type' => 'submit',
      '#value' => t('Create Link'),
      '#attributes' => [
        'tabindex' => 3,
        'class' => ['create-link'],
      ],
      '#ajax' => [
        'callback' => '::submitAjaxCall',
        'wrapper' => 'shorten-link',
        'event' => 'click',
      ],
    ];

    $element['clipboard'] = [
      '#type' => 'button',
      '#value' => $this->t('Copy'),
      '#attributes' => ['class' => ['clipboard-button', 'visually-hidden']],
    ];

    $form['wrapper'] += $element;

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
   * Shorten Url form ajax callback.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return mixed
   *   Returns element form wrapper.
   */
  public function submitAjaxCall(array &$form, FormStateInterface $form_state) {
    $short_link_generator = \Drupal::service('hel_tpm_url_shortener.short_url_service');

    $path = $form_state->getValue('current_path');

    $url = $short_link_generator->generateShortLink($path);

    if (empty($url)) {
      return $form['wrapper'];
    }

    $form['wrapper']['submit']['#attributes']['class'][] = 'visually-hidden';

    // Remove hidden class from clipboard element.
    $clipboard_classes = array_flip($form['wrapper']['clipboard']['#attributes']['class']);
    unset($form['wrapper']['clipboard']['#attributes']['class'][$clipboard_classes['visually-hidden']]);

    $form['wrapper']['link'] = [
      'type' => '#markup',
      '#prefix' => '<div class="short-link-result"',
      '#suffix' => '</div>',
      '#markup' => sprintf(
        '<span class="short-link">%s</span><div class="clipboard-status"></div>', $url->getShortUrl()
      )
    ];


    return $form['wrapper'];
  }

}
