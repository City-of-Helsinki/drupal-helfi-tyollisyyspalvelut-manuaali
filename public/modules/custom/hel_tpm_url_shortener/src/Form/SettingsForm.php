<?php

namespace Drupal\hel_tpm_url_shortener\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Hel TPM Url shortener settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hel_tpm_url_shortener_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hel_tpm_url_shortener.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base url'),
      '#default_value' => $this->config('hel_tpm_url_shortener.settings')->get('base_url'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('hel_tpm_url_shortener.settings')
      ->set('base_url', $form_state->getValue('base_url'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
