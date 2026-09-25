<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_dates;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Converts weekday times to scalar wall-clock values, without timezone shifts.
 */
final class WeekdayAndTimeValue {

  /**
   * Normalizes time values in a structured weekdays array.
   *
   * This method iterates through a multi-dimensional array of weekdays
   * and applies time normalization to any 'time' entries found within it.
   * The normalization is performed through the `self::time()` method.
   *
   * @param array $weekdays
   *   A multi-dimensional array where each element may contain a 'time'
   *   key that stores an array of time values to be normalized.
   *
   * @return array
   *   Returns the modified weekdays array with all 'time' entries normalized.
   */
  public static function normalize(array $weekdays): array {
    foreach ($weekdays as &$rows) {
      foreach ($rows as &$row) {
        foreach ($row['time'] ?? [] as $key => $time) {
          $row['time'][$key] = self::time($time);
        }
      }
    }
    return $weekdays;
  }

  /**
   * Converts a given time to the 'H:i:s' format or returns NULL if invalid.
   *
   * This method processes various input formats such as arrays, objects,
   * serialized objects, and strings to properly extract and format time
   * values. If the input time is invalid or empty, it returns NULL.
   *
   * @param mixed $time
   *   The input time to be processed. This can be an array containing
   *   an 'object' key, an instance of DrupalDateTime or \DateTimeInterface,
   *   a serialized object (\__PHP_Incomplete_Class), or a string formatted
   *   as a valid time.
   *
   * @return string|null
   *   Returns the formatted time as a string in 'H:i:s' format, or NULL if
   *   the input is invalid or empty.
   */
  public static function time(mixed $time): ?string {
    if (is_array($time)) {
      $time = $time['object'] ?? NULL;
    }
    if ($time === NULL || $time === '') {
      return NULL;
    }
    if ($time instanceof DrupalDateTime || $time instanceof \DateTimeInterface) {
      return $time->format('H:i:s');
    }
    // Old serialized objects must be decoded with allowed_classes = FALSE.
    // Read the underlying date without recreating obsolete dynamic properties.
    if ($time instanceof \__PHP_Incomplete_Class) {
      $properties = (array) $time;
      if (($properties['__PHP_Incomplete_Class_Name'] ?? '') === DrupalDateTime::class) {
        return self::time($properties["\0*\0dateTimeObject"] ?? NULL);
      }
      if (in_array($properties['__PHP_Incomplete_Class_Name'] ?? '', ['DateTime', 'DateTimeImmutable'], TRUE)) {
        return (new \DateTimeImmutable($properties['date'], new \DateTimeZone($properties['timezone'])))->format('H:i:s');
      }
    }
    if (is_string($time) && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D', $time)) {
      return strlen($time) === 5 ? $time . ':00' : $time;
    }
    throw new \UnexpectedValueException('Invalid weekday and time field time value.');
  }

}
