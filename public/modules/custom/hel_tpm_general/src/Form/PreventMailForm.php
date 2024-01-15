<?php

declare(strict_types = 1);

namespace Drupal\hel_tpm_general\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hel_tpm_general\PreventMailUtility;

/**
 * Shows block mail status.
 */
class PreventMailForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hel_tpm_general.block_mail_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['block_mail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Block sending mail'),
      '#default_value' => PreventMailUtility::get(),
      '#description' => t("Normally this should not be checked! This setting is stored using State API."),
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
    if ($form_state->getValue('block_mail') === 1) {
      PreventMailUtility::set();
    }
    else {
      PreventMailUtility::set(FALSE);
    }
  }

}
