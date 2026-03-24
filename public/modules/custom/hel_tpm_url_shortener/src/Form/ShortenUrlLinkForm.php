<?php

namespace Drupal\hel_tpm_url_shortener\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\hel_tpm_url_shortener\Ajax\ShortUrlCommand;
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
      '#disabled' => TRUE,
      '#attached' => [
        'library' => [
          'hel_tpm_url_shortener/url_shortener',
        ],
      ],
    ];
    $element['label'] = [
      '#type' => 'html_tag',
      '#tag' => 'label',
      '#attributes' => ['class' => ['label']],
      '#value' => $this->t('Create link to search result'),

    ];
    $element['submit'] = [
      '#type' => 'button',
      '#value' => $this->t('Copy Link To Search Results'),
      '#attributes' => [
        'tabindex' => 3,
        'class' => ['create-link'],
        'title' => $this->t('Copy Link To Search Results'),
      ],
      '#ajax' => [
        'callback' => '::customSubmitAjax',
        'wrapper' => 'shorten-link',
        'event' => 'click',
      ],
    ];

    $element['clipboard-status'] = [
      '#markup' => '<div class="clipboard-status popup pill--small-message-base pill--small-message-url pill--small-message--add pill--padding-small-text small-font font-primary-blue font-weight-bold"><div class="popup-title"></div></div>',
    ];

    $element['hide_short_link'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['hide-short-link', 'visually-hidden'],
        'title' => $this->t('Hide Short Link'),
      ],
    ];
    $element['link'] = [
      '#type' => 'markup',
      '#markup' => '<div class="short-link-result visually-hidden"><div class="short-link"></div></div>',
      '#attributes' => [
        'class' => ['short-link'],
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
   * Handles AJAX submission for the form.
   *
   * @param array $form
   *   The form structure array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response with commands to process the shortened URL.
   */
  public function customSubmitAjax(array &$form, FormStateInterface $form_state) {
    $path = $this->getCurrentPath($form_state);
    $url = $this->urlShortener->generateShortLink($path);
    $response = new AjaxResponse();
    $response->addCommand(new ShortUrlCommand($url->getShortUrl()));
    return $response;
  }

  /**
   * Retrieves the current path based on the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object containing the current form's data.
   *
   * @return string
   *   The translated URL string derived from the current path.
   */
  protected function getCurrentPath(FormStateInterface $form_state) {
    $path = $form_state->getValue('current_path');
    $url = Url::fromUserInput($path);
    return $url->toString();

  }

}
