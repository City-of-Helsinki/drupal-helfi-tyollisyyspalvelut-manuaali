<?php

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\date_recur\DateRange;
use Drupal\date_recur\Entity\DateRecurInterpreterInterface;
use Drupal\date_recur\Plugin\Field\FieldFormatter\DateRecurBasicFormatter;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;

/**
 * Plugin implementation of the 'ServiceDateField' formatter.
 *
 * @FieldFormatter(
 *   id = "hel_tpm_service_dates_date_recur_formatter",
 *   label = @Translation("Service date recur formatter"),
 *   field_types = {
 *     "date_recur"
 *   }
 * )
 */
class ServiceDateFieldFormatter extends DateRecurBasicFormatter {

  /**
   * Generate the output appropriate for a field item.
   *
   * @param \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem $item
   *   A field item.
   * @param int $maxOccurrences
   *   Maximum number of occurrences to show for this field item.
   *
   * @return array
   *   A render array for a field item.
   */
  protected function viewItem(DateRecurItem $item, $maxOccurrences): array {
    $cacheability = new CacheableMetadata();
    $build = [
      '#theme' => 'date_recur_basic_formatter',
      '#is_recurring' => $item->isRecurring(),
    ];

    $startDate = $item->start_date;
    /** @var \Drupal\Core\Datetime\DrupalDateTime|null $endDate */
    $endDate = $item->end_date ?? $startDate;
    if (!$startDate || !$endDate) {
      return $build;
    }

    $build['#date'] = $this->buildDateRangeValue($startDate, $endDate, FALSE);

    // Render the rule.
    if ($item->isRecurring() && $this->getSetting('interpreter')) {
      /** @var string|null $interpreterId */
      $interpreterId = $this->getSetting('interpreter');
      if ($interpreterId && ($interpreter = $this->dateRecurInterpreterStorage->load($interpreterId))) {
        assert($interpreter instanceof DateRecurInterpreterInterface);
        $rules = $item->getHelper()->getRules();
        $plugin = $interpreter->getPlugin();
        $cacheability->addCacheableDependency($interpreter);
        $langcode = $interpreter->language();
        $build['#interpretation'] = $plugin->interpret($rules, $langcode->getId());
      }
    }

    // Occurrences are generated even if the item is not recurring.
    $build['#occurrences'] = array_map(
      function (DateRange $occurrence): array {
        $startDate = DrupalDateTime::createFromDateTime($occurrence->getStart());
        $endDate = DrupalDateTime::createFromDateTime($occurrence->getEnd());
        return $this->buildDateRangeValue(
          $startDate,
          $endDate,
          TRUE
        );
      },
      $this->getOccurrences($item, $maxOccurrences)
    );

    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Builds a date range suitable for rendering.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $startDate
   *   The start date.
   * @param \Drupal\Core\Datetime\DrupalDateTime $endDate
   *   The end date.
   * @param bool $isOccurrence
   *   Whether the range is an occurrence of a repeating value.
   *
   * @return array
   *   A render array.
   */
  protected function buildDateRangeValue(DrupalDateTime $startDate, DrupalDateTime $endDate, $isOccurrence): array {
    $this->formatType = $isOccurrence ? $this->getSetting('occurrence_format_type') : $this->getSetting('format_type');
    $startDateString = $this->buildDateWithIsoAttribute($startDate);

    // Show the range if start and end are different, otherwise only start date.
    if ($startDate->getTimestamp() === $endDate->getTimestamp()) {
      return $startDateString;
    }
    else {
      // Start date and end date are different.
      $this->formatType = $startDate->format('Ymd') == $endDate->format('Ymd') ?
        $this->getSetting('same_end_date_format_type') :
        $this->getSetting('occurrence_format_type');
      $endDateString = $this->buildDateWithIsoAttribute($endDate);
      return [
        'start_date' => $startDateString,
        'separator' => ['#plain_text' => $this->getSetting('separator')],
        'end_date' => $endDateString,
      ];
    }
  }

}
