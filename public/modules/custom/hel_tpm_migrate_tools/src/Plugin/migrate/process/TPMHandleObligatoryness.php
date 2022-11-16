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
 *   id = "tpm_handle_obligatoryness"
 * )
 */
class TPMHandleObligatoryness extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (stripos($value, 'Palveluun osallistuminen on velvoittavaa') !== FALSE) {
      $value = 'option1';
    }
    elseif (stripos($value, 'Suunnitelman noudattamatta jättäminen voi vaikuttaa') !== FALSE) {
      $value = 'option2';
    }
    elseif (stripos($value, 'Palveluun osallistuminen ei ole velvoittavaa') !== FALSE) {
      $value = 'option3';
    }
    return $value;
  }

}
