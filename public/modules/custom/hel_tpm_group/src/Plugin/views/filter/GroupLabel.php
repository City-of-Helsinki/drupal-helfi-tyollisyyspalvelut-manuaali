<?php

declare(strict_types = 1);

namespace Drupal\hel_tpm_group\Plugin\views\filter;

use Drupal\Component\Utility\Html;
use Drupal\group\Entity\Group;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Drupal\views\ViewExecutable;

/**
 * Filter by parent group using the group label.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("group_label_filter")
 */
class GroupLabel extends ManyToOne {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL): void {
    parent::init($view, $display, $options);
    $this->valueTitle = t('Group');
    $this->definition['options callback'] = [$this, 'generateOptions'];
  }

  /**
   * Generates options.
   *
   * @return string[]
   *   Available options for the filter.
   */
  protected function generateOptions(): array {
    $gids = \Drupal::entityQuery('group')
      ->accessCheck(TRUE)
      ->execute();
    $groups = Group::loadMultiple($gids);
    $options = [];

    foreach ($groups as $gid => $group) {
      $value = $group?->get('label')?->getString();
      if (!empty($value)) {
        $options[$gid] = Html::escape($value);
      }
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    return $options;
  }

}
