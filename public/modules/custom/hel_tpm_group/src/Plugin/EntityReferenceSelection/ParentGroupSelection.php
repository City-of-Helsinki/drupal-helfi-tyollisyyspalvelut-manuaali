<?php

namespace Drupal\hel_tpm_group\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\gcontent_moderation\GroupStateTransitionValidation;
use Drupal\ggroup\GroupHierarchyManagerInterface;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\node\NodeInterface;
use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin description.
 *
 * @EntityReferenceSelection(
 *   id = "hel_tpm_group_parent_group_selection",
 *   label = @Translation("Group parent group selection"),
 *   group = "hel_tpm_group_parent_group_selection",
 *   entity_types = {"group"},
 *   weight = 0
 * )
 */
class ParentGroupSelection extends UserSelection {

  /**
   * @var \Drupal\ggroup\GroupHierarchyManagerInterface
   */
  private $groupHierarchyManager;

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  private $routeMatch;

  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              EntityTypeManagerInterface $entity_type_manager,
                              ModuleHandlerInterface $module_handler,
                              AccountInterface $current_user,
                              Connection $connection,
                              EntityFieldManagerInterface $entity_field_manager = NULL,
                              EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
                              EntityRepositoryInterface $entity_repository = NULL,
                              GroupHierarchyManagerInterface $group_hierarchy_manager,
                              RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $module_handler, $current_user,  $connection,  $entity_field_manager, $entity_type_bundle_info, $entity_repository);

    $this->groupHierarchyManager = $group_hierarchy_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('database'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity.repository'),
      $container->get('ggroup.group_hierarchy_manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'filter_users_without_publish' => FALSE
      ] + parent::defaultConfiguration(); // TODO: Change the autogenerated stub
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state); // TODO: Change the autogenerated stub
    $form['include_supergroup'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include super groups')
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    $configuration = $this->getConfiguration();

    if (!empty($configuration['entity'])) {
      $entity = $configuration['entity'];
    }

    $include_supergroups = empty($configuration['include_supergroup']) ? FALSE : $configuration['include_supergroup'];

    $groups = $this->getGroups($entity, $include_supergroups);

    // Append uids to query condition.
    if (!empty($groups)) {
      $query->condition('id', $groups, 'IN');
    }

    return $query;
  }

  /**
   * Get groups from node.
   *
   * @param $node
   *
   * @return array
   */
  private function getGroups($node, $include_supergroups) {
    $groups = [];
    if (!$node instanceof NodeInterface) {
      return $groups;
    }

    if ($node->isNew()) {
      $group = $this->routeMatch->getParameter('group');
      if (empty($group)) {
        return [];
      }
      $groups[] = $this->routeMatch->getParameter('group')->id();
    }
    else {
      // Get groups from node.
      foreach ($node->entitygroupfield->referencedEntities() as $group) {
        if (empty($group)) {
          continue;
        }
        $groups[$group->getGroup()->id()] = $group->getGroup()->id();
      }
    }

    // Return if no groups found.
    if (empty($groups)) {
      return [];
    }

    if ($include_supergroups) {
      // Fetch parent groups for subgroups.
      foreach ($groups as $group) {
        $super_groups = $this->groupHierarchyManager->getGroupSupergroupIds($group);
        if (empty($super_groups)) {
          continue;
        }
        $groups = array_merge($groups, $super_groups);
      }
    }

    return $groups;
  }

  /**
   * @param $match
   * @param $match_operator
   * @param $limit
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $target_type = $this->getConfiguration()['target_type'];

    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $result = $query->execute();

    if (empty($result)) {
      return [];
    }

    $options = [];
    $entities = $this->entityTypeManager->getStorage($target_type)->loadMultiple($result);
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      $options[$bundle][$entity_id] = Html::escape($entity->label());
    }

    return $options;
  }

}
