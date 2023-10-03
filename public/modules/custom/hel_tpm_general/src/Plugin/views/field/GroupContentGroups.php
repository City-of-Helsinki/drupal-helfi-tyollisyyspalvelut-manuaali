<?php

namespace Drupal\hel_tpm_general\Plugin\views\field;

use Drupal\ggroup\GroupHierarchyManagerInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Group Content Groups field handler.
 *
 * @ViewsField("hel_tpm_general_group_super_groups")
 *
 * @DCG
 * The plugin needs to be assigned to a specific table column through
 * hook_views_data() or hook_views_data_alter().
 * For non-existent columns (i.e. computed fields) you need to override
 * self::query() method.
 */
class GroupContentGroups extends FieldPluginBase {

  /**
   * Group hierarchy manager service.
   *
   * @var \Drupal\ggroup\GroupHierarchyManagerInterface
   */
  protected $groupMembershipLoader;

  /**
   * Constructs a new GroupContentGroups instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ggroup\GroupHierarchyManagerInterface $ggroup_membership_loader
   *   Group hierarchy manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GroupHierarchyManagerInterface $ggroup_membership_loader) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->groupMembershipLoader = $ggroup_membership_loader;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ggroup.group_hierarchy_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $group_hierarchy = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
    ];
    $relationships = $values->_relationship_entities;
    if (empty($relationships['gid'])) {
      return '';
    }
    $group = $relationships['gid'];
    $super_groups = $this->groupMembershipLoader->getGroupSupergroups($group->id());
    if (empty($super_groups)) {
      return '';
    }

    foreach ($super_groups as $key => $sgroup) {
      $group_hierarchy['#items'][$key] = $sgroup->toLink()->toString();
    }

    return $this->renderer->render($group_hierarchy);
  }

}
