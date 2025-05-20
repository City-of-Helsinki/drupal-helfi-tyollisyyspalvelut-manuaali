<?php

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime_range\DateTimeRangeConstantsInterface;
use Drupal\datetime_range\Plugin\Field\FieldFormatter\DateRangeCustomFormatter;

/**
 * Custom date range formatter.
 */
#[FieldFormatter(
  id: 'hel_tpm_service_dates_custom_daterange',
  label: new TranslatableMarkup('Custom Daterange'),
  field_types: [
    'daterange',
  ],
)]
class CustomDateRangeFormatter extends DateRangeCustomFormatter {

  /**
   * Renders the start and end date and time with possible format adjustments.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $start_date
   *   The starting date and time object.
   * @param string $separator
   *   The separator used to format the date range.
   * @param \Drupal\Core\Datetime\DrupalDateTime $end_date
   *   The ending date and time object.
   *
   * @return array
   *   An array of rendered date elements, with adjustments if the start and end
   *   date are the same day.
   */
  protected function renderStartEnd(DrupalDateTime $start_date, string $separator, DrupalDateTime $end_date): array {
    $element = parent::renderStartEnd($start_date, $separator, $end_date);
    if ($start_date->format('Y-m-d') === $end_date->format('Y-m-d')) {
      $element[DateTimeRangeConstantsInterface::END_DATE]['#markup'] = $end_date->format('H:i');
    }
    return $element;
  }

}
