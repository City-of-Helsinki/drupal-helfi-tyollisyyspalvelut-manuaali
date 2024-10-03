<?php

namespace Drupal\hel_tpm_group\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates GroupUniqueTitle constraint.
 */
class GroupUniqueTitleConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if ($value?->getEntityTypeId() != 'group') {
      return;
    }
    /** @var \Drupal\group\Entity\Group $value */

    $entityQuery = \Drupal::entityQuery('group')
      ->condition('label', $value->label())
      ->accessCheck(FALSE);
    // Exclude current group from the query so that using the same title is
    // allowed when editing an existing group.
    if (!empty($id = $this->context->getRoot()->getEntity()->id())) {
      $entityQuery->condition('id', $id, '!=');
    }
    $groupCount = $entityQuery->count()->execute();

    if ($groupCount > 0) {
      $this->context->addViolation($constraint->message);
    }
  }

}
