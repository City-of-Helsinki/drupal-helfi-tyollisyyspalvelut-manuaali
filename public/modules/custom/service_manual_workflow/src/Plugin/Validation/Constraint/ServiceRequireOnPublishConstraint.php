<?php

declare(strict_types=1);

namespace Drupal\service_manual_workflow\Plugin\Validation\Constraint;

use Drupal\require_on_publish\Plugin\Validation\Constraint\RequireOnPublish;

/**
 * Provides a Service Require on Publish constraint.
 *
 * @Constraint(
 *   id = "service_require_on_publish",
 *   label = @Translation("Service Require on Publish", context = "Validation"),
 * )
 *
 * @see https://www.drupal.org/node/2015723.
 */
final class ServiceRequireOnPublishConstraint extends RequireOnPublish {}
