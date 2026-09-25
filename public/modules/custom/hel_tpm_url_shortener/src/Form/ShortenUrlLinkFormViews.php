<?php

namespace Drupal\hel_tpm_url_shortener\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a Hel TPM Url shortener form.
 */
class ShortenUrlLinkFormViews extends ShortenUrlLinkForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hel_tpm_url_shortener_shorten_url_link_views';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['wrapper']['#prefix'] = '<div class="hel-tpm-url-shortener-shorten-url-link-views">';
    $form['wrapper']['#suffix'] = '</div>';

    $element = &$form['wrapper'];
    $ajax_url = Url::fromRoute('hel_tpm_url_shortener.shortener_ajax');
    $element['submit']['#type'] = 'html_tag';
    $element['submit']['#tag'] = 'button';
    $element['submit']['#ajax'] = [];
    $element['submit']['#attributes']['data-ajax-url'] = $ajax_url->toString();
    $element['submit']['#attached']['library'] = ['hel_tpm_url_shortener/url_shortener_views'];
    $element['#attached']['drupalSettings']['ajaxTrustedUrl'][$ajax_url->toString()] = TRUE;

    return $form;
  }

}
