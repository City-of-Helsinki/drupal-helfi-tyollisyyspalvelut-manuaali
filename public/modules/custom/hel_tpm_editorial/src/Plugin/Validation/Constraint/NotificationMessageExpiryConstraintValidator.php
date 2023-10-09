<?php

namespace Drupal\hel_tpm_editorial\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the NotificationMessageExpiry constraint.
 */
class NotificationMessageExpiryConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    // This is a single-item field so we only need to
    // validate the first item.
    $item = $items->first();

    if ($item && strtotime($item->value) > strtotime('+1 month')) {
      $this->context->addViolation($constraint->incorrectDurationFormat);
    }
  }

}
