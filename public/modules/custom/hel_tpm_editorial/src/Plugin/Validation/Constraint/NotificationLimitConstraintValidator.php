<?php

namespace Drupal\hel_tpm_editorial\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the NotificationLimitConstraint constraint.
 */
class NotificationLimitConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if ($value?->getEntityTypeId() != 'notification_message') {
      return;
    }
    /** @var \Drupal\notification_message\Entity\NotificationMessage $value */
    if ($value->getBundleEntityTypeEntity()?->id() != $constraint->type) {
      return;
    }
    // Only limit creating new notification messages.
    if (!$value->isNew()) {
      return;
    }

    $entityQuery = \Drupal::entityQuery('notification_message')
      ->condition('type', $constraint->type);
    $notificationCount = $entityQuery->count()->execute();

    if ($notificationCount >= $constraint->limit) {
      $this->context->addViolation($constraint->message, [
        '%limit' => $constraint->limit,
      ]);
    }
  }
}
