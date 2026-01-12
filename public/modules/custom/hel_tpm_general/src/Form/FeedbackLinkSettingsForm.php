<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Helsinki TPM General settings for this site.
 */
final class FeedbackLinkSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hel_tpm_general_feedback_link_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['hel_tpm_general.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['feedback_link_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Feedback link URL'),
      '#default_value' => $this->config('hel_tpm_general.settings')->get('feedback_link_url'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('hel_tpm_general.settings')
      ->set('feedback_link_url', $form_state->getValue('feedback_link_url'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
