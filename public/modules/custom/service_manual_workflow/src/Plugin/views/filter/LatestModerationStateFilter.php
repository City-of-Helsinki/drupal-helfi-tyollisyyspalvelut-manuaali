<?php

namespace Drupal\service_manual_workflow\Plugin\views\filter;

use Drupal\content_moderation\Plugin\views\filter\ModerationStateFilter;
use Drupal\views\Views;

/**
 * Provides a filter for the moderation state of an entity.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("latest_moderation_state_filter")
 */
class LatestModerationStateFilter extends ModerationStateFilter {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    $this->valueOptions = parent::getValueOptions();
  }

  /**
   * {@inheritdoc}
   */
  public function ensureMyTable() {
    if (!isset($this->tableAlias)) {
      $table_alias = $this->query->ensureTable($this->table, $this->relationship);

      // Join the moderation states of the content via the
      // ContentModerationState field revision table, joining either the entity
      // field data or revision table. This allows filtering states against
      // either the default or latest revision, depending on the relationship of
      // the filter.
      $left_entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());
      $entity_type = $this->entityTypeManager->getDefinition('content_moderation_state');
      $configuration = [
        'table formula' => $this->contentModerationTableFormula(),
        'field' => 'content_entity_id',
        'left_table' => $table_alias,
        'left_field' => $left_entity_type->getKey('id'),
        'extra' => [
          [
            'field' => 'content_entity_type_id',
            'value' => $left_entity_type->id(),
          ],
          [
            'field' => 'content_entity_id',
            'left_field' => $left_entity_type->getKey('id'),
          ],
        ],
      ];
      if ($left_entity_type->isTranslatable()) {
        $configuration['extra'][] = [
          'field' => $entity_type->getKey('langcode'),
          'left_field' => $left_entity_type->getKey('langcode'),
        ];
      }
      $join = Views::pluginManager('join')->createInstance('standard', $configuration);

      $this->tableAlias = $this->query->addRelationship('content_moderation_state', $join, 'content_moderation_state_field_revision');
    }

    return $this->tableAlias;
  }

  /**
   * Create content moderation table formula for filtering values from latest revision.
   *
   * @return mixed
   */
  private function contentModerationTableFormula() {
    $select = $this->view->query->getConnection()->select('content_moderation_state_field_revision', 'cmsfr');
    $select->fields('cmsfr');

    $subquery = $this->view->query->getConnection()->select('content_moderation_state_field_revision');
    $subquery->addExpression('MAX(content_entity_revision_id)');
    $subquery->groupBy('content_entity_id');

    $select->condition('cmsfr.content_entity_revision_id', $subquery, 'IN');

    return $select;
  }

}
