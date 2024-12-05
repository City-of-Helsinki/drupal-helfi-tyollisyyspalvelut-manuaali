<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_user_expiry\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hel_tpm_user_expiry\SettingsUtility;

/**
 * Shows user expiry settings.
 */
class UserExpirySettings extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hel_tpm_user_expiry.user_expiry_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['user_expiry_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable user expiration'),
      '#default_value' => SettingsUtility::getUserExpirationStatus(),
      '#description' => $this->t("Unchecking this will prevent user expire cron job to add more users to expiry queue. The value is stored using the State API, so configuration updates will not affect it."),
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('user_expiry_enabled') === 1) {
      SettingsUtility::enableUserExpiration();
    }
    else {
      SettingsUtility::disableUserExpiration();
    }
  }

}
