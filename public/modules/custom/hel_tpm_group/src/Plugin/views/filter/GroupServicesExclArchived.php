<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group\Plugin\views\filter;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Filter groups by service count excluding archived.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("group_services_count_excl_archived_filter")
 */
class GroupServicesExclArchived extends GroupContentCount {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);
    $this->tableAlias = 'group_services_excl_archived';
    $this->realField = 'service_count';
  }

}
