<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hel_tpm_general\PreventMailUtility;

/**
 * Manage block mail settings.
 */
class PreventMailForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hel_tpm_general_block_mail_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t("Use these settings to temporarily prevent sending automatic emails. Importing configurations during deployment does not affect these settings.") .
      '<p><strong>' . $this->t('Normally these options should not be checked.') . '</strong></p>',
      '#prefix' => '<div class="description">',
      '#suffix' => '</div>',
    ];

    $form['all_mails'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Block sending mail'),
      '#default_value' => PreventMailUtility::isBlocked(),
      '#description' => $this->t("Prevents sending all emails that can be altered using the hook_mail_alter() hook."),
    ];

    $form['message_templates'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Block mails by message templates'),
    ];

    $form['message_templates']['ready_to_publish'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Block mails for ready to publish services'),
      '#default_value' => PreventMailUtility::isReadyToPublishServicesBlocked(),
      '#description' => $this->t("Prevents automatic emails from being sent when services are marked as ready to publish. Blocked emails will not be sent at a later time.") . "<p>" .
      $this->t("Affects message template: @template.", [
        '@template' => "group_ready_to_publish_notificat",
      ]) . "</p>",
    ];

    $form['message_templates']['published_services'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Block mails for published services'),
      '#default_value' => PreventMailUtility::isPublishedServicesBlocked(),
      '#description' => $this->t("Prevents automatic emails from being sent when services are published. Blocked emails will not be sent at a later time.") . "<p>" .
      $this->t("Affects message template: @template.", [
        '@template' => "content_has_been_published",
      ]) . "</p>",
    ];

    $form['message_templates']['update_reminder_services'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Block service update reminder mails'),
      '#default_value' => PreventMailUtility::isUpdateReminderBlocked(),
      '#description' => $this->t("Prevents sending service reminder emails.") . "<p>" .
      $this->t("Affects message templates: @template1 and @template2.", [
        '@template1' => "hel_tpm_update_reminder_service",
        '@template2' => "hel_tpm_update_reminder_service2",
      ]) . "</p>",
    ];

    $form['message_templates']['update_reminder_outdated'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Block outdated service mails'),
      '#default_value' => PreventMailUtility::isUpdateReminderOutdatedBlocked(),
      '#description' => $this->t("Prevents emails from being sent to inform users about outdated services. Services are not outdated until the emails are successfully sent.") . "<p>" .
      $this->t("Affects message template: @template.", [
        '@template' => "hel_tpm_update_reminder_outdated",
      ]) . "</p>",
    ];

    $form['message_templates']['services_missing_updaters'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Block services missing updaters mails'),
      '#default_value' => PreventMailUtility::isServiceMissingUpdatersBlocked(),
      '#description' => $this->t("Prevents emails from being sent to inform users about services with missing updaters.") . "<p>" .
      $this->t("Affects message template: @template.", [
        '@template' => "services_missing_updaters",
      ]) . "</p>",
    ];

    $form['message_templates']['user_account_expiry'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Block user account expiry mails'),
      '#default_value' => PreventMailUtility::isUserExpirationBlocked(),
      '#description' => $this->t("Prevents emails from being sent to inform users about expiring user accounts. User expiration does not proceed until the emails are successfully sent.") .
      "<p>" . $this->t("Note: User expiry can be disabled from a separate settings page. This option blocks emails that are already in the queue, while the other option prevents new user-expiry tasks from being added to the queue.") . "</p>" .
      "<p>" . $this->t("Affects message templates: @template1, @template2 and @template3.", [
        '@template1' => "1st_user_account_expiry_reminder",
        '@template2' => "2nd_user_account_expiry_reminder",
        '@template3' => "hel_tpm_user_expiry_blocked",
      ]) . "</p>",
    ];

    $form['message_templates']['group_account_blocked'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Block mails for deactivated group accounts'),
      '#default_value' => PreventMailUtility::isDeactivatedGroupAccountBlocked(),
      '#description' => $this->t("Prevents sending notification emails to users when their account is deactivated because it no longer belongs to any group. The accounts are deactivated regardless of this choice.") . "<p>" .
      $this->t("Affects message template: @template.", [
        '@template' => "hel_tpm_group_account_blocked",
      ]) . "</p>",
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
    PreventMailUtility::block((bool) $form_state->getValue('all_mails'));
    PreventMailUtility::blockReadyToPublishServices((bool) $form_state->getValue('ready_to_publish'));
    PreventMailUtility::blockPublishedServices((bool) $form_state->getValue('published_services'));
    PreventMailUtility::blockUpdateReminder((bool) $form_state->getValue('update_reminder_services'));
    PreventMailUtility::blockServiceOutdated((bool) $form_state->getValue('update_reminder_outdated'));
    PreventMailUtility::blockServiceMissingUpdaters((bool) $form_state->getValue('services_missing_updaters'));
    PreventMailUtility::blockUserExpiration((bool) $form_state->getValue('user_account_expiry'));
    PreventMailUtility::blockDeactivatedGroupAccount((bool) $form_state->getValue('group_account_blocked'));
  }

}
