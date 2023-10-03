<?php

namespace Drupal\hel_tpm_migrate_tools\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Format date as a timestamp for node dates.
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "tpm_format_date"
 * )
 */
class TPMFormatDate extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $pattern = '%(\d{1,2})[./-]+(\d{1,2})[./-]+(\d{2,4})%';
    if (preg_match($pattern, $value, $matches)) {
      $value = mktime(0, 0, 0, $matches[2], $matches[1], $matches[3]);
    }
    else {
      $value = time();
    }
    return $value;
  }

}
