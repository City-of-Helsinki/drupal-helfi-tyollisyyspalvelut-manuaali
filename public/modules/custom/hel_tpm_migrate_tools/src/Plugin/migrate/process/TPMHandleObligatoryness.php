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
 *   id = "tpm_handle_obligatoryness"
 * )
 */
class TPMHandleObligatoryness extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $out_value = NULL;
    if (stripos($value, 'Palveluun osallistuminen on velvoittavaa') !== FALSE) {
      $out_value = 'option1';
    }
    elseif (stripos($value, 'Suunnitelman noudattamatta jättäminen voi vaikuttaa') !== FALSE) {
      $out_value = 'option2';
    }
    elseif (stripos($value, 'Palveluun osallistuminen ei ole velvoittavaa') !== FALSE) {
      $out_value = 'option3';
    }
    return $out_value;
  }

}
