<?php

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime_range\DateTimeRangeConstantsInterface;
use Drupal\datetime_range\Plugin\Field\FieldFormatter\DateRangeDefaultFormatter;

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
class CustomDateRangeFormatter extends DateRangeDefaultFormatter {

  /**
   * {@inheritdoc}
   */
  protected function renderStartEndWithIsoAttribute(DrupalDateTime $start_date, string $separator, DrupalDateTime $end_date): array {
    $element = parent::renderStartEndWithIsoAttribute($start_date, $separator, $end_date);
    if ($start_date->format('Y-m-d') === $end_date->format('Y-m-d')) {
      $element[DateTimeRangeConstantsInterface::END_DATE]['#text'] = $end_date->format('H:i');
    }
    return $element;
  }

}
