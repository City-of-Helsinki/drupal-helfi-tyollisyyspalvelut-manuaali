<?php

namespace Drupal\hel_tpm_editorial\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Ensures that there are only limited number of notification messages.
 *
 * @Constraint(
 *   id = "NotificationLimit",
 *   label = @Translation("Limit notification messages", context = "Validation"),
 * )
 */
class NotificationLimitConstraint extends Constraint {

  /**
   * The notification type ID.
   *
   * @var string
   */
  public string $type;

  /**
   * The notification limit.
   *
   * @var int
   */
  public int $limit;

  /**
   * The default violation message.
   *
   * @var string
   */
  public string $message = "To add a new notification you must first remove an existing one of the same type. There can be no more than %limit notifications at a time.";

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['type', 'limit'];
  }
}
