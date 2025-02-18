<?php

namespace Drupal\hel_tpm_group\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Connection;
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
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\hel_tpm_group\GroupSelectionTrait;
use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin description.
 *
 * @EntityReferenceSelection(
 *   id = "hel_tpm_group_editor_user_selection",
 *   label = @Translation("Group editor user selection"),
 *   group = "hel_tpm_group_editor_user_selection",
 *   entity_types = {"user"},
 *   weight = 0
 * )
 */
class GroupUserSelection extends UserSelection {

  use GroupSelectionTrait;

  /**
   * Group roles.
   *
   * @var string[]
   */
  private $groupRoles = [
    'editor',
    'group_admin',
    'administrator',
  ];

  /**
   * Group membership loader service.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  private $groupMembershipLoader;

  /**
   * Group transition validator.
   *
   * @var \Drupal\gcontent_moderation\GroupStateTransitionValidation
   */
  private $groupStateTransitionValidator;

  /**
   * Group hierarchy manager service.
   *
   * @var \Drupal\ggroup\GroupHierarchyManagerInterface
   */
  private $groupHierarchyManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    AccountInterface $current_user,
    Connection $connection,
    ?EntityFieldManagerInterface $entity_field_manager = NULL,
    ?EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
    ?EntityRepositoryInterface $entity_repository = NULL,
    GroupMembershipLoaderInterface $group_membership_loader,
    GroupStateTransitionValidation $group_state_transition_validator,
    GroupHierarchyManagerInterface $group_hierarchy_manager,
    RouteMatchInterface $route_match,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $module_handler, $current_user, $connection, $entity_field_manager, $entity_type_bundle_info, $entity_repository);

    $this->groupMembershipLoader = $group_membership_loader;
    $this->groupStateTransitionValidator = $group_state_transition_validator;
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
      $container->get('group.membership_loader'),
      $container->get('gcontent_moderation.state_transition_validation'),
      $container->get('ggroup.group_hierarchy_manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'filter_users_without_publish' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['filter_users_without_publish'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Filter users without publish permission'),
      '#default_value' => !empty($configuration['filter_users_without_publish']) ? $configuration['filter_users_without_publish'] : FALSE,
    ];
    $form['include_supergroup_members'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include members from super groups'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    $configuration = $this->getConfiguration();
    $include_supergroups = empty($configuration['include_supergroup_members']) ? FALSE : $configuration['include_supergroup_members'];

    if (!empty($configuration['entity'])) {
      $entity = $configuration['entity'];
    }

    $groups = $this->getGroups($entity, $include_supergroups, TRUE);
    $members = $this->getMembers($groups);

    // Append uids to query condition.
    if (!empty($members)) {
      $query->condition('uid', $members, 'IN');
    }
    else {
      $query->condition('uid', $this->currentUser->id());
    }

    return $query;
  }

  /**
   * Get members for groups.
   *
   * @param array $groups
   *   Array of groups.
   *
   * @return array|int|string|null
   *   Return all members from given groups.
   */
  private function getMembers(array $groups) {
    $members = [];

    if (empty($groups)) {
      return NULL;
    }
    foreach ($groups as $group) {
      if ($group instanceof GroupRelationshipInterface) {
        $group = $group->getGroup();
      }
      // Validate referenced entity is of type group content interface.
      if ($group instanceof GroupInterface) {
        $members = array_merge($members, $this->groupMembers($group));
      }
    }

    return $members;
  }

  /**
   * {@inheritdoc}
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
      $options[$bundle][$entity_id] = Html::escape($entity->getEmail());
    }

    return $options;
  }

  /**
   * Get group members with editor / admin roles.
   *
   * @param \Drupal\group\Entity\GroupRelationshipInterface $group
   *   Group content entity object.
   *
   * @return array|int|string|null
   *   Array of group members.
   */
  private function groupMembers(GroupInterface $group) : array {
    $uids = [];
    $rids = $this->buildGroupRoleIds($group);
    $members = $group->getMembers($rids);

    $configuration = $this->getConfiguration();
    // If no memberships are found for roles return empty array.
    if (empty($members)) {
      return $uids;
    }

    $mock_service = $this->mockService();
    foreach ($members as $member) {
      $id = $member->getUser()->id();
      // Skip anonymous user.
      if ($id == 0) {
        continue;
      }

      var_dump($configuration);
      if ($configuration['filter_users_without_publish'] === TRUE) {
        $allowed = $this->groupStateTransitionValidator->allowedTransitions($member->getUser(), $mock_service, [$group]);
        var_dump($id);
        var_dump($allowed);
        // Skip loop is user doesn't have publish permission.
        if (empty($allowed['publish'])) {
          continue;
        }
      }
      $uids[$id] = $id;
    }

    return $uids;
  }

  /**
   * Helper method to format proper group role ids.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group object.
   *
   * @return array
   *   Array of group ids.
   */
  private function buildGroupRoleIds(GroupInterface $group) : array {
    $rids = [];
    $group_type = $group->getGroupType()->id();
    foreach ($this->groupRoles as $role) {
      $rids[] = sprintf('%s-%s', $group_type, $role);
    }
    return $rids;
  }

}
