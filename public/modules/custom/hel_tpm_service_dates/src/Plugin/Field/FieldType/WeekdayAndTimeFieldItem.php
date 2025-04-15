<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\MapFieldItemList;
use Drupal\Core\Field\Plugin\Field\FieldType\MapItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the 'hel_tpm_service_dates_weekday_and_time_field' field type.
 */
#[FieldType(
  id: "hel_tpm_service_dates_weekday_and_time_field",
  label: new TranslatableMarkup("Weekday and time field"),
  description: new TranslatableMarkup("An entity field for storing a serialized array of values."),
  default_widget: "hel_tpm_service_dates_weekday_and_time_field",
  default_formatter: "hel_tpm_service_dates_weekday_and_time_field_default",
  no_ui: FALSE,
  list_class:  MapFieldItemList::class,
)]

final class WeekdayAndTimeFieldItem extends MapItem {

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    $constraints = parent::getConstraints();
    // @todo Add more constraints here.
    return $constraints;
  }

}
