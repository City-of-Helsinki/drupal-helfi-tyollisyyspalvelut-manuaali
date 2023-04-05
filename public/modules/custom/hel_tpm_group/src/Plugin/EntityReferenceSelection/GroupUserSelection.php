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
use Drupal\Core\Session\AccountInterface;
use Drupal\gcontent_moderation\GroupStateTransitionValidation;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;
use Drupal\user\UserInterface;
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

  private $group_roles = [
    'editor',
    'group_admin',
    'administrator'
  ];

  /**
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  private $group_membership_loader;

  private $groupStateTransitionValidator;

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
                              GroupMembershipLoaderInterface $group_membership_loader,
                              GroupStateTransitionValidation $groupStateTransitionValidator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $module_handler, $current_user,  $connection,  $entity_field_manager, $entity_type_bundle_info, $entity_repository);

    $this->group_membership_loader = $group_membership_loader;
    $this->groupStateTransitionValidator = $groupStateTransitionValidator;
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
      $container->get('gcontent_moderation.state_transition_validation')
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
    $configuration = $this->getConfiguration();
    $form = parent::buildConfigurationForm($form, $form_state); // TODO: Change the autogenerated stub
    $form['filter_users_without_publish'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Filter users without publish permission'),
      '#default_value' => !empty($configuration['filter_users_without_publish']) ? $configuration['filter_users_without_publish'] : FALSE
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    $configuration = $this->getConfiguration();
    $members = [];

    // Users with administer users permission can list and view all users.
    if ($this->currentUser->hasPermission('administer users')) {
    //  return $query;
    }

    if (!empty($configuration['entity'])) {
      $entity = $configuration['entity'];
    }

    // Validate referenced entity is of type group content interface.
    if ($entity instanceof GroupContentInterface) {
      $group = $entity->getGroup();
      if (!empty($group)) {
        $members = $this->groupMembers($group);
      }
    }
    else {
      $members = $this->getUserGroupMembers($this->currentUser);
    }

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
      $options[$bundle][$entity_id] = Html::escape($entity->getEmail());
    }

    return $options;
  }

  /**
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return array
   */
  public function getUserGroupMembers(AccountInterface $account) {
    $members = [];
    $memberships = $this->group_membership_loader->loadByUser($account);
    if (empty($memberships)) {
      return [];
    }

    foreach ($memberships as $membership) {
      $group = $membership->getGroup();
      $members = array_merge($members, $this->groupMembers($group));
    }

    return $members;
  }

  /**
   * Get group members with editor / admin roles.
   *
   * @param \Drupal\group\Entity\GroupContentInterface $entity
   *
   * @return array|int|string|null
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
      if ($configuration['filter_users_without_publish']) {
        $allowed = $this->groupStateTransitionValidator->allowedTransitions($member->getUser(), $mock_service, [$group]);
        if (empty($allowed['publish'])) {
          continue;
        }
      }
      $uids[$id] = $id;
    }

    return $uids;
  }

  private function mockService() {
    return $this->entityTypeManager->getStorage('node')->create([
      'type' => 'service',
      'name' => 'MockService'
    ]);
  }
  /**
   * Helper method to format proper group role ids.
   *
   * @param $group
   *
   * @return array
   */
  private function buildGroupRoleIds(GroupInterface $group) : array {
    $rids = [];
    $group_type = $group->getGroupType()->id();
    foreach ($this->group_roles as $role) {
      $rids[] = sprintf('%s-%s', $group_type, $role);
    }
    return $rids;
  }
}
