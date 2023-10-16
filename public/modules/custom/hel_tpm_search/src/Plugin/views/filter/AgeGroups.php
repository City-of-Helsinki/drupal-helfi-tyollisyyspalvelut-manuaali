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
      [$values['from'], $values['to']] = explode("-", $item);
      if (empty($values['from']) || empty($values['to']) || !is_numeric($values['from']) || !is_numeric($values['to'])) {
        continue;
      }

      // Filter using the age range: the service age range should match either
      // the lower or the upper limit, or both.
      /** @var \Drupal\search_api\Query\ConditionGroupInterface $itemCondition */
      $itemCondition = $this->query->createConditionGroup('OR');

      // Check the lower limit.
      /** @var \Drupal\search_api\Query\ConditionGroupInterface $lowerCondition */
      $lowerCondition = $this->query->createConditionGroup('AND');
      $lowerCondition->addCondition('field_age_from', $values['from'], ">=");
      $lowerCondition->addCondition('field_age_from', $values['to'], "<=");
      $itemCondition->addConditionGroup($lowerCondition);

      // Check the upper limit.
      /** @var \Drupal\search_api\Query\ConditionGroupInterface $upperCondition */
      $upperCondition = $this->query->createConditionGroup('AND');
      $upperCondition->addCondition('field_age_to', $values['from'], ">=");
      $upperCondition->addCondition('field_age_to', $values['to'], "<=");
      $itemCondition->addConditionGroup($upperCondition);

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
