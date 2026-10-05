<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_dropdown_filter\Traits;

use Drupal\views\Views;

/**
 * Configures the bef_test view to use the dropdown filter widget.
 *
 * Uses the bef_test module from Better Exposed Filters. Classes using this
 * trait must also use BetterExposedFiltersTrait.
 */
trait DropdownFilterTestTrait {

  /**
   * Uses the dropdown filter widget for the letters and location filters.
   *
   * The letters filter is a multiple value list field filter. The location
   * filter is a taxonomy filter on a hierarchical vocabulary and its terms
   * are grouped by parent.
   *
   * @param array $letters_settings
   *   Additional widget settings for the letters filter.
   * @param bool $location_multiple
   *   Whether the location filter allows multiple values.
   */
  protected function useDropdownFilter(array $letters_settings = [], bool $location_multiple = TRUE): void {
    $filters = 'display.default.display_options.filters';
    \Drupal::configFactory()->getEditable('views.view.bef_test')
      ->set("$filters.field_bef_letters_value.expose.description", 'Choose letters')
      ->set("$filters.field_bef_location_target_id.expose.multiple", $location_multiple)
      ->save();

    $view = Views::getView('bef_test');
    $this->setBetterExposedOptions($view, [
      'filter' => [
        'field_bef_letters_value' => ['plugin_id' => 'hel_tpm_dropdown_filter'] + $letters_settings,
        'field_bef_location_target_id' => [
          'plugin_id' => 'hel_tpm_dropdown_filter',
          'term_optgroup' => TRUE,
        ],
      ],
    ]);
  }

  /**
   * Gets the ID of a bef_test location term.
   *
   * @param string $name
   *   The term name.
   *
   * @return string
   *   The term ID.
   */
  protected function getLocationTid(string $name): string {
    $tids = \Drupal::entityQuery('taxonomy_term')
      ->accessCheck(FALSE)
      ->condition('vid', 'bef_test_location')
      ->condition('name', $name)
      ->execute();
    return (string) reset($tids);
  }

}
