<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_mail_tools\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that prevent message creation option is not set.
 *
 * @Constraint(
 *   id = "PreventMessageConstraint",
 *   label = @Translation("Prevent message constraint", context = "Validation"),
 *   type = "string"
 * )
 */
class PreventMessageConstraint extends Constraint {

  /**
   * Constraint message.
   *
   * @var string
   */
  public $message = 'Creating message template is disabled in Block mail settings.';

}
