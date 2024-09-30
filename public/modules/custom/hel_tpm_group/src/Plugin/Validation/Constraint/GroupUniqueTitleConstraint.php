<?php

namespace Drupal\hel_tpm_group\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Ensures there's no existing group with the same title.
 *
 * @Constraint(
 *   id = "GroupUniqueTitle",
 *   label = @Translation("Group unique title", context = "Validation"),
 * )
 */
class GroupUniqueTitleConstraint extends Constraint {

  /**
   * The violation message.
   *
   * @var string
   */
  public string $message = "A group with the same title already exists. Use a unique title for each group.";

}
