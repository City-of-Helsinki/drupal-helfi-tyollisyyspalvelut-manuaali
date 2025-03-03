<?php

namespace Drupal\hel_tpm_group\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ggroup\GroupHierarchyManagerInterface;
use Drupal\hel_tpm_group\GroupSelectionTrait;
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
class ParentGroupSelection extends GroupSelection {

  use GroupSelectionTrait;

  /**
   * Group hierarchy manager service.
   *
   * @var \Drupal\ggroup\GroupHierarchyManagerInterface
   */
  private $groupHierarchyManager;

  /**
   * Route matcher service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

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
    EntityFieldManagerInterface $entity_field_manager,
    EntityRepositoryInterface $entity_repository,
    GroupHierarchyManagerInterface $group_hierarchy_manager,
    RouteMatchInterface $route_match,
    ?EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $module_handler, $current_user, $entity_field_manager, $entity_type_bundle_info, $entity_repository);
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
      $container->get('entity_field.manager'),
      $container->get('entity.repository'),
      $container->get('ggroup.group_hierarchy_manager'),
      $container->get('current_route_match'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['include_supergroup'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include super groups'),
      '#default_value' => !empty($config['include_supergroup']) ? $config['include_supergroup'] : NULL,
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
    if (!empty($groups)) {
      $query->condition('id', $groups, 'IN');
    }

    return $query;
  }

}
