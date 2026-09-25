<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_stats\Plugin\Field\FieldType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\NumericItemBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

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
final class DaysSinceStateChangeItem extends NumericItemBase {

  /**
   * Whether the value has been calculated.
   *
   * @var bool
   */
  protected $isCalculated = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'unsigned' => FALSE,
        // Valid size property values include:
        // 'tiny', 'small', 'medium', 'normal'and 'big'.
      'size' => 'normal',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'type' => 'int',
          // Expose the 'unsigned' setting in the field item schema.
          'unsigned' => $field_definition->getSetting('unsigned'),
          // Expose the 'size' setting in the field item schema. For instance,
          // supply 'big' as a value to produce a 'bigint' type.
          'size' => $field_definition->getSetting('size'),
        ],
      ],
    ];
  }

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
    return FALSE;
  }

  /**
   * Calculates the value of the field and sets it.
   */
  protected function ensureCalculated() {
    if (!$this->isCalculated) {
      $entity = $this->getEntity();
      if ($entity->isNew()) {
        $value = 0;
      }
      else {
        $value = $this->calculateDaysSinceLastStateChange($entity);
      }
      $this->setValue(intval($value));
      $this->isCalculated = TRUE;
    }
  }

  /**
   * Calculate days since last state change.
   */
  protected function calculateDaysSinceLastStateChange(EntityInterface $entity) {
    if (!$entity->isRevisionTranslationAffected()) {
      foreach ($entity->getTranslationLanguages() as $language) {
        $entity = $entity->getTranslation($language->getId());
        if ($entity->isRevisionTranslationAffected()) {
          break;
        }
      }
    }
    $service = \Drupal::service('hel_tpm_service_stats.revision_history');
    return $service->getTimeSinceLastStateChange($entity);
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Integer value'))
      ->setRequired(TRUE);

    return $properties;
  }

}
