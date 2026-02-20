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

    if (PreventMailUtility::isTemplateBlocked($value->bundle())) {
      $this->context->addViolation($constraint->message);
    }
  }

}
