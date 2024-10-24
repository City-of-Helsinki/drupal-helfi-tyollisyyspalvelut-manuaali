<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_stats\Plugin\Field\FieldType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\IntegerItem;

/**
 * Defines the 'hel_tpm_service_stats_days_in_rtb' field type.
 *
 * @FieldType(
 *   id = "hel_tpm_service_stats_days_since_state_change",
 *   label = @Translation("Days since last state change"),
 *   description = @Translation("Computed field which shows days since last moderation state change"),
 *   default_widget = "number",
 *   default_formatter = "number_integer",
 *   constraints = {}
 * )
 */
final class DaysSinceStateChangeItem extends IntegerItem {

  /**
   * Whether the value has been calculated.
   *
   * @var bool
   */
  protected $isCalculated = FALSE;

  /**
   * {@inheritdoc}
   */
  public function __get($name) {
    $this->ensureCalculated();
    return parent::__get($name);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $this->ensureCalculated();
    if ((string) $this->value === 0) {
      return FALSE;
    }
    return parent::isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $this->ensureCalculated();
    return parent::getValue();
  }

  /**
   * Calculates the value of the field and sets it.
   */
  protected function ensureCalculated() {
    if (!$this->isCalculated) {
      $entity = $this->getEntity();
      if ($entity->isNew()) {
        return;
      }
      $value = $this->calculateDaysSinceLastStateChange($entity);
      $this->setValue($value);
      $this->isCalculated = TRUE;
    }
  }

  protected function calculateDaysSinceLastStateChange(EntityInterface $entity) {
    $service = \Drupal::service('hel_tpm_service_stats.revision_history');
    return $service->getTimeSinceLastStateChange($entity);
  }

}
