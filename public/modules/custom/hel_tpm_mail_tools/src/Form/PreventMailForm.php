<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_mail_tools\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hel_tpm_mail_tools\Utility\PreventMailUtility;

/**
 * Manage block mail settings.
 */
class PreventMailForm extends FormBase {

  private const MESSAGE_OPTION_PREFIX = 'message_';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hel_tpm_mail_tools_block_mail_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Use these settings to temporarily prevent sending automatic emails. Importing configurations during deployment does not affect these settings.') .
      '<p><strong>' . $this->t('Normally these options should not be checked.') . '</strong></p>',
      '#prefix' => '<div class="description">',
      '#suffix' => '</div>',
    ];

    $form['all_mails'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Block sending mail'),
      '#default_value' => PreventMailUtility::isMailBlocked(),
      '#description' => $this->t('Prevents sending all emails that can be altered using the hook_mail_alter() hook.'),
    ];

    $form['message_templates'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Block mails by message templates'),
    ];

    $formMessageOptions = [
      PreventMailUtility::SERVICES_READY_TO_PUBLISH => [
        'title' => $this->t('Block mails for ready to publish services'),
        'descriptions' => [
          $this->t("Prevents automatic emails from being sent when services are marked as ready to publish. Blocked emails will not be sent at a later time."),
        ],
      ],
      PreventMailUtility::SERVICES_PUBLISHED => [
        'title' => $this->t('Block mails for published services'),
        'descriptions' => [
          $this->t('Prevents automatic emails from being sent when services are published. Blocked emails will not be sent at a later time.'),
        ],
      ],
      PreventMailUtility::SERVICES_UPDATE_REMINDER => [
        'title' => $this->t('Block service update reminder mails'),
        'descriptions' => [
          $this->t('Prevents sending service reminder emails.'),
        ],
      ],
      PreventMailUtility::SERVICES_OUTDATED_REMINDER => [
        'title' => $this->t('Block outdated service mails'),
        'descriptions' => [
          $this->t('Prevents emails from being sent to inform users about outdated services. Services are not outdated until the emails are successfully sent.'),
        ],
      ],
      PreventMailUtility::SERVICES_MISSING_UPDATERS => [
        'title' => $this->t('Block services missing updaters mails'),
        'descriptions' => [
          $this->t('Prevents emails from being sent to inform users about services with missing updaters.'),
        ],
      ],
      PreventMailUtility::USER_EXPIRATION => [
        'title' => $this->t('Block user account expiry mails'),
        'descriptions' => [
          $this->t('Prevents emails from being sent to inform users about expiring user accounts. User expiration does not proceed until the emails are successfully sent.'),
          $this->t('Note: User expiry can be disabled from a separate settings page. This option blocks emails that are already in the queue, while the other option prevents new user-expiry tasks from being added to the queue.'),
        ],
      ],
      PreventMailUtility::GROUP_ACCOUNT_BLOCKED => [
        'title' => $this->t('Block mails for deactivated group accounts'),
        'descriptions' => [
          $this->t('Prevents sending notification emails to users when their account is deactivated because it no longer belongs to any group. The accounts are deactivated regardless of this choice.'),
        ],
      ],
    ];

    foreach ($formMessageOptions as $flag => $values) {
      $form['message_templates'][self::MESSAGE_OPTION_PREFIX . $flag] = [
        '#type' => 'checkbox',
        '#title' => $values['title'],
        '#default_value' => PreventMailUtility::isMessageBlocked($flag),
        '#description' => $this->printDescription($values['descriptions'], PreventMailUtility::getTemplates($flag)),
      ];
    }

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
    PreventMailUtility::blockMail((bool) $form_state->getValue('all_mails'));

    foreach ($form_state->getValues() as $id => $value) {
      if (str_starts_with($id, self::MESSAGE_OPTION_PREFIX)) {
        PreventMailUtility::blockMessage(substr($id, strlen(self::MESSAGE_OPTION_PREFIX)), (bool) $value);
      }
    }
  }

  /**
   * Outputs form item description for template checkboxes.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup[] $descriptions
   *   Array of description markup.
   * @param array $affects
   *   Names of the templates.
   *
   * @return string
   *   Description text.
   */
  private function printDescription(array $descriptions, array $affects): string {
    $output = '';
    foreach ($descriptions as $description) {
      $output .= '<p>' . $description . '</p>';
    }

    if (empty($affects)) {
      return $output;
    }

    $output .= '<p>';
    if (count($affects) === 1) {
      $output .= $this->t("Affects message template:");
    }
    else {
      $output .= $this->t("Affects message templates:");
    }
    $output .= '<ul>';
    foreach ($affects as $affect) {
      $output .= '<li>' . $affect . '</li>';
    }
    $output .= '</ul></p>';

    return $output;
  }

}
