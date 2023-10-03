<?php

namespace Drupal\hel_tpm_editorial\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the notification message conforms our requirements.
 *
 * @Constraint(
 *   id = "NotificationMessageExpiry",
 *   label = @Translation("Notification Message expiry", context = "Validation"),
 * )
 */
class NotificationMessageExpiryConstraint extends Constraint {

  /**
   * The message that will be shown if the format is incorrect.
   *
   * @var string
   */
  public $incorrectDurationFormat = 'The expiry date can be no longer than 1 month.';

}
