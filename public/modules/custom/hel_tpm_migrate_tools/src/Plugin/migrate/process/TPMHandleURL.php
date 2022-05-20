<?php

namespace Drupal\hel_tpm_migrate_tools\Plugin\migrate\process;

use Drupal\Core\Database\Database;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\Component\Utility\UrlHelper;

/**
 * Format date as a timestamp for node dates.
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "tpm_handle_url"
 * )
 */
class TPMHandleURL extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Rudimentary fix to fix some easy cases missing the http prefix.
    if (stripos($value, 'http') !== 0) {
      $value = 'http://' . $value;
    }

    if (!UrlHelper::isValid($value, TRUE)) {
      $value = '';
    }
    return $value;
  }

}
