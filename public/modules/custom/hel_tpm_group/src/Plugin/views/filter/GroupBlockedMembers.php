<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group\Plugin\views\filter;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Filter groups by blocked member count.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("group_blocked_members_count_filter")
 */
class GroupBlockedMembers extends GroupContentCount {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);
    $this->tableAlias = 'blocked_members';
    $this->realField = 'blocked_count';
  }

}
