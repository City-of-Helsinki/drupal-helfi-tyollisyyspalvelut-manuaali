<?php

declare(strict_types=1);

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
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);
    $this->valueTitle = $this->t('Age groups');
    $this->definition['options callback'] = [$this, 'generateOptions'];
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $ranges = $this->getSelectedRanges();
    if (empty($ranges)) {
      return;
    }

    /** @var \Drupal\search_api\Query\ConditionGroupInterface $rangeOrGroupCondition */
    $rangeOrGroupCondition = $this->query->createAndAddConditionGroup('OR');

    /** @var \Drupal\search_api\Query\ConditionGroupInterface $itemsCondition */
    $itemsCondition = $this->query->createConditionGroup('OR');
    foreach ($ranges as [$from, $to]) {
      // Filter using the age range.
      /** @var \Drupal\search_api\Query\ConditionGroupInterface $itemCondition */
      $itemCondition = $this->query->createConditionGroup('AND');

      // Selected upper-end can't be lower than service age range lower-end.
      $itemCondition->addCondition('field_age_from', $to, "<=");
      // Selected lower-end can't be higher than service age range upper-end.
      $itemCondition->addCondition('field_age_to', $from, ">=");

      $itemsCondition->addConditionGroup($itemCondition);
    }
    $rangeOrGroupCondition->addConditionGroup($itemsCondition);

    // Filter using the age groups.
    $rangeOrGroupCondition->addCondition('field_age_groups', ['no_age_restriction' => 'no_age_restriction'], 'IN');
  }

  /**
   * Gets the selected age ranges.
   *
   * Only the predefined options are accepted, so values from the URL can't
   * be used to build arbitrary ranges.
   *
   * @return int[][]
   *   The selected ranges as [from, to] pairs.
   */
  protected function getSelectedRanges(): array {
    if (empty($this->value) || !is_array($this->value)) {
      return [];
    }

    $options = $this->generateOptions();
    $ranges = [];
    foreach ($this->value as $item) {
      if (!is_string($item) || !isset($options[$item]) || !preg_match('/^(\d+)-(\d+)$/D', $item, $matches)) {
        continue;
      }
      $ranges[$item] = [(int) $matches[1], (int) $matches[2]];
    }
    return array_values($ranges);
  }

  /**
   * Generates options.
   *
   * @return string[]
   *   Available options for the filter.
   */
  protected function generateOptions(): array {
    return [
      '16-29' => $this->t("16–29-year-olds"),
      '30-54' => $this->t("30–54-year-olds"),
      '55-70' => $this->t("55–70-year-olds"),
    ];
  }

}
