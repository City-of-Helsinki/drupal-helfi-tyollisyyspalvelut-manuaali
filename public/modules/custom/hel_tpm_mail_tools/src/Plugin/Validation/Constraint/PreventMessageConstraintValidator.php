<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_mail_tools\Plugin\Validation\Constraint;

use Drupal\hel_tpm_mail_tools\Utility\PreventMailUtility;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the PreventMessageConstraint constraint.
 */
class PreventMessageConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if ($value->getEntityTypeId() !== 'message' || !$value->isNew()) {
      return;
    }

    $isTemplateBlocked = match ($value->bundle()) {
      'group_ready_to_publish_notificat' => PreventMailUtility::isReadyToPublishServicesBlocked(),
      'content_has_been_published' => PreventMailUtility::isPublishedServicesBlocked(),
      'hel_tpm_update_reminder_service', 'hel_tpm_update_reminder_service2' => PreventMailUtility::isUpdateReminderBlocked(),
      'hel_tpm_update_reminder_outdated' => PreventMailUtility::isUpdateReminderOutdatedBlocked(),
      'services_missing_updaters' => PreventMailUtility::isServiceMissingUpdatersBlocked(),
      '1st_user_account_expiry_reminder', '2nd_user_account_expiry_reminder', 'hel_tpm_user_expiry_blocked' => PreventMailUtility::isUserExpirationBlocked(),
      'hel_tpm_group_account_blocked' => PreventMailUtility::isDeactivatedGroupAccountBlocked(),
      default => FALSE,
    };

    if ($isTemplateBlocked) {
      $this->context->addViolation($constraint->message);
    }
  }

}
