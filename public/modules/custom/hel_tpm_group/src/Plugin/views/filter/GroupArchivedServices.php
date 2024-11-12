<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group\Plugin\views\filter;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Filter groups by archived service count.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("group_archived_services_count_filter")
 */
class GroupArchivedServices extends GroupContentCount {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);
    $this->tableAlias = 'group_archived_services';
    $this->realField = 'service_count';
  }

}
