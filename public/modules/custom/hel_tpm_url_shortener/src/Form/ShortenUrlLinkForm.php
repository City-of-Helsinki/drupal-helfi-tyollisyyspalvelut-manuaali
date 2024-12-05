<?php

namespace Drupal\hel_tpm_url_shortener\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hel_tpm_url_shortener\ShortUrlService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Hel TPM Url shortener form.
 */
class ShortenUrlLinkForm extends FormBase {

  /**
   * Url shortener.
   *
   * @var \Drupal\hel_tpm_url_shortener\ShortUrlService
   */
  private ShortUrlService $urlShortener;

  public function __construct(ShortUrlService $url_shortener) {
    $this->urlShortener = $url_shortener;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('hel_tpm_url_shortener.short_url_service')
    );
  }

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
      '#attributes' => ['id' => 'shorten-link', 'class' => ['shorten-link']],
    ];

    $element['current_path'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['current-path']],
      '#attached' => [
        'library' => [
          'hel_tpm_url_shortener/url_shortener',
        ],
      ],
    ];
    $element['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Short Link'),
      '#attributes' => [
        'tabindex' => 3,
        'class' => ['create-link'],
        'title' => $this->t('Create Short Link'),
      ],
      '#ajax' => [
        'callback' => '::submitAjaxCall',
        'wrapper' => 'shorten-link',
        'event' => 'click',
      ],
    ];

    $element['clipboard'] = [
      '#type' => 'button',
      '#value' => $this->t('Copy to clipboard'),
      '#attributes' => [
        'class' => ['clipboard-button', 'visually-hidden'],
        'title' => $this->t('Copy to clipboard'),
      ],
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
    $path = $form_state->getValue('current_path');
    $url = $this->urlShortener->generateShortLink($path);

    if (empty($url)) {
      return $form['wrapper'];
    }

    $form['wrapper']['submit']['#attributes']['class'][] = 'visually-hidden';

    // Remove hidden class from clipboard element.
    $clipboard_classes = array_flip($form['wrapper']['clipboard']['#attributes']['class']);
    unset($form['wrapper']['clipboard']['#attributes']['class'][$clipboard_classes['visually-hidden']]);

    $form['wrapper']['link'] = [
      '#type' => '#markup',
      '#prefix' => '<div class="short-link-result"',
      '#suffix' => '</div>',
      '#markup' => sprintf(
        '<div class="short-link">%s</div>', $url->getShortUrl()
      ),
    ];
    $form['wrapper']['clipboard-status'] = [
      '#markup' => '<div class="clipboard-status popup pill--small-message-base pill--small-message-url pill--small-message--add pill--padding-small-text small-font font-primary-blue font-weight-bold"><div class="popup-title"></div></div>',
    ];

    return $form['wrapper'];
  }

}
