<?php

namespace Drupal\hel_tpm_group\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Views;

/**
 * Provides the number of active group members.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("hel_tpm_group_active_member_count")
 */
class GroupActiveMemberCount extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->field_alias = $this->query->addField('active_members', 'active_count');
  }

  /**
   * {@inheritdoc}
   */
  public function ensureMyTable() {
    if (!isset($this->tableAlias)) {
      $configuration = [
        'table formula' => $this->activeUsersTableFormula(),
        'field' => 'group_id',
        'left_table' => 'groups_field_data',
        'left_field' => 'id',
        'operator' => '=',
      ];
      /** @var \Drupal\views\Plugin\views\join\JoinPluginBase $join */
      $join = Views::pluginManager('join')->createInstance('standard', $configuration);
      $this->tableAlias = $this->query->addRelationship('active_members', $join, 'groups_field_data');
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
   * Get table formula for active member count.
   *
   * @return mixed
   *   Select query.
   */
  private function activeUsersTableFormula(): mixed {
    /** @var \Drupal\Core\Database\Query\SelectInterface $select */
    $select = $this->view->query->getConnection()->select('group_relationship_field_data', 'grfd');
    $select->addField('grfd', 'gid', 'group_id');
    $select->condition('grfd.plugin_id', 'group_membership');
    $select->addJoin('INNER', 'users_field_data', 'ufd', 'ufd.uid = grfd.entity_id');
    $select->condition('ufd.status', 1);
    $select->addExpression("COUNT(ufd.status)", 'active_count');
    $select->groupBy('group_id');
    return $select;
  }

}
