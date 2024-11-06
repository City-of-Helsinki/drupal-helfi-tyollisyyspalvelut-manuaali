<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group\Plugin\views\filter;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\views\ViewExecutable;

/**
 * Filter groups by service count.
 *
 * When extending, set $this->tableAlias and $this->realField.
 *
 * @ingroup views_filter_handlers
 */
abstract class GroupContentCount extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);
    $this->definition['options callback'] = [$this, 'generateOptions'];
  }

  /**
   * {@inheritdoc}
   */
  public function operators(): array {
    $operators = parent::operators();
    unset($operators['not in']);
    return $operators;
  }

  /**
   * Adds filter to query.
   *
   * @return void
   *   -
   */
  public function query(): void {
    if (empty($this->value) || !is_array($this->value)) {
      return;
    }
    $this->ensureMyTable();

    switch ($this->value[0]) {
      case 'yes':
        $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", 1, '>=');
        break;

      case 'no':
        $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", NULL, 'IS NULL');
        break;
    }
  }

  /**
   * Generate options.
   *
   * @return string[]
   *   Filter options.
   */
  protected function generateOptions(): array {
    return [
      'yes' => t('Yes'),
      'no' => t('No'),
    ];
  }

}
