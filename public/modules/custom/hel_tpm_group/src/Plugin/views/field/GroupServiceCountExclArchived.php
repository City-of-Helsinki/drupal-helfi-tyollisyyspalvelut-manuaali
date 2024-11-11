<?php

namespace Drupal\hel_tpm_group\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Views;

/**
 * Provides the number of group services excluding archived.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("hel_tpm_group_service_count_excl_archived")
 */
class GroupServiceCountExclArchived extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->field_alias = $this->query->addField('group_services_excl_archived', 'service_count');
  }

  /**
   * {@inheritdoc}
   */
  public function ensureMyTable() {
    if (!isset($this->tableAlias)) {
      $configuration = [
        'table formula' => $this->groupServicesExclArchivedTableFormula(),
        'field' => 'group_id',
        'left_table' => 'groups_field_data',
        'left_field' => 'id',
        'operator' => '=',
      ];
      /** @var \Drupal\views\Plugin\views\join\JoinPluginBase $join */
      $join = Views::pluginManager('join')->createInstance('standard', $configuration);
      $this->tableAlias = $this->query->addRelationship('group_services_excl_archived', $join, 'groups_field_data');
    }
    return $this->tableAlias;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    if ($value === NULL) {
      $value = 0;
    }
    return $this->sanitizeValue($value);
  }

  /**
   * Get table formula for group service count excluding archived.
   *
   * @return mixed
   *   Select query.
   */
  private function groupServicesExclArchivedTableFormula(): mixed {
    /** @var \Drupal\Core\Database\Query\SelectInterface $select */
    $select = $this->view->query->getConnection()->select('group_relationship_field_data', 'grfd');
    $select->addField('grfd', 'gid', 'group_id');
    $select->condition('grfd.plugin_id', 'group_node:service');
    $select->addJoin('INNER', 'content_moderation_state_field_data', 'cmsfd', 'cmsfd.content_entity_id = grfd.entity_id');
    $select->condition('cmsfd.moderation_state', 'archived', '<>');
    $select->addExpression("COUNT(entity_id)", 'service_count');
    $select->groupBy('group_id');
    return $select;
  }

}
