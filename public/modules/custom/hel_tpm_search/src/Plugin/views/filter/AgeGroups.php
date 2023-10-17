<?php

declare(strict_types = 1);

namespace Drupal\hel_tpm_search\Plugin\views\filter;

use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Drupal\views\ViewExecutable;

/**
 * Filter services by age groups.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("age_groups_filter")
 */
class AgeGroups extends ManyToOne {
  use SearchApiFilterTrait;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL): void {
    parent::init($view, $display, $options);
    $this->valueTitle = t('Age groups');
    $this->definition['options callback'] = [$this, 'generateOptions'];
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    if (empty($this->value) || !is_array($this->value)) {
      return;
    }

    /** @var \Drupal\search_api\Query\ConditionGroupInterface $rangeOrGroupCondition */
    $rangeOrGroupCondition = $this->query->createAndAddConditionGroup('OR');

    /** @var \Drupal\search_api\Query\ConditionGroupInterface $itemsCondition */
    $itemsCondition = $this->query->createConditionGroup('OR');
    foreach ($this->value as $item) {
      if (!preg_match('/^\d{2}-\d{2}$/', $item)) {
        continue;
      }
      [$values['from'], $values['to']] = explode("-", $item);
      if (empty($values['from']) || empty($values['to']) || !is_numeric($values['from']) || !is_numeric($values['to'])) {
        continue;
      }

      // Filter using the age range.
      /** @var \Drupal\search_api\Query\ConditionGroupInterface $itemCondition */
      $itemCondition = $this->query->createConditionGroup('AND');

      // Selected upper-end can't be lower than service age range lower-end.
      $itemCondition->addCondition('field_age_from', $values['to'], "<=");
      // Selected lower-end can't be higher than service age range upper-end.
      $itemCondition->addCondition('field_age_to', $values['from'], ">=");

      $itemsCondition->addConditionGroup($itemCondition);
    }
    $rangeOrGroupCondition->addConditionGroup($itemsCondition);

    // Filter using the age groups.
    $rangeOrGroupCondition->addCondition('field_age_groups', ['no_age_restriction' => 'no_age_restriction'], 'IN');
  }

  /**
   * Generates options.
   *
   * @return string[]
   *   Available options for the filter.
   */
  protected function generateOptions(): array {
    return [
      '16-30' => t("16–30-year-olds"),
      '31-54' => t("31–54-year-olds"),
      '55-70' => t("55–70-year-olds"),
    ];
  }

}
