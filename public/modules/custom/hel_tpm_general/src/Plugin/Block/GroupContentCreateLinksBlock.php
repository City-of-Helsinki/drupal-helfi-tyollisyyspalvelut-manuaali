<?php

namespace Drupal\hel_tpm_general\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\group\GroupMembershipLoader;
use Drupal\hel_tpm_general\Access\GroupNodeCreateAccessService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a group content create links block.
 *
 * @Block(
 *   id = "hel_tpm_general_group_content_create_links",
 *   admin_label = @Translation("Group Content Create Links"),
 *   category = @Translation("Custom")
 * )
 */
class GroupContentCreateLinksBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\group\GroupMembershipLoader
   */
  protected $groupMembershipLoader;

  /**
   * @var \Drupal\user\Plugin\views\argument_default\CurrentUser
   */
  protected $currentUser;

  /**
   * Constructs a new GroupContentCreateLinksBlock instance.
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, GroupMembershipLoader $group_membership_loader, AccountInterface $current_user, GroupNodeCreateAccessService $group_node_add_access_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->groupMembershipLoader = $group_membership_loader;
    $this->currentUser = $current_user;
    $this->groupNodeAddAccessService = $group_node_add_access_service;
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
      $container->get('group.membership_loader'),
      $container->get('current_user'),
      $container->get('hel_tpm_general.group_node_add_access_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return $this->groupNodeAddAccessService->hasAccess($account, 'group_node:service');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['content'] = [
      '#type' => 'link',
      '#title' => $this->t('Add service'),
      '#url' => Url::fromRoute('hel_tpm_general.group.node.add.service'),
      '#attributes' => ['class' => ['add-service-button', 'button']]
    ];
    return $build;
  }

}
