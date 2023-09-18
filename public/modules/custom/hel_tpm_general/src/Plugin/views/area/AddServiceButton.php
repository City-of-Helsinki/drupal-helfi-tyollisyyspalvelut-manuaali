<?php

namespace Drupal\hel_tpm_general\Plugin\views\area;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\hel_tpm_general\Access\GroupNodeCreateAccessService;
use Drupal\hel_tpm_group\CurrentGroup;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Add Service button field handler.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("hel_tpm_general_add_service_button")
 */
class AddServiceButton extends AreaPluginBase {

  /**
   * Group node add access service.
   *
   * @var \Drupal\hel_tpm_general\Access\GroupNodeCreateAccessService
   */
  private $groupNodeAddAccessService;

  /**
   * Current group service.
   *
   * @var \Drupal\hel_tpm_group\CurrentGroup
   */
  private $currentGroup;

  /**
   * Current user account interface.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * Add service button constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\hel_tpm_general\Access\GroupNodeCreateAccessService $group_node_add_access_service
   *   Group node access service.
   * @param \Drupal\hel_tpm_group\CurrentGroup $current_group
   *   Current group services.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user account.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GroupNodeCreateAccessService $group_node_add_access_service, CurrentGroup $current_group, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->groupNodeAddAccessService = $group_node_add_access_service;
    $this->currentGroup = $current_group;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('hel_tpm_general.group_node_add_access_service'),
      $container->get('hel_tpm_group.current_group'),
      $container->get('current_user')
    );
  }

  /**
   * Access callback.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account interface.
   *
   * @return bool
   *   Return TRUE or FALSE depending on if user has access for selected plugin.
   */
  public function access(AccountInterface $account) {
    $access = $this->groupNodeAddAccessService->hasAccess($account, 'group_node:service');
    if ($access instanceof AccessResultAllowed) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    return $this->addServiceGroupListLink();
  }

  /**
   * Build add service link list from groups.
   *
   * @return array
   *   Render array of create service links.
   */
  private function addServiceGroupListLink() {
    $url = $this->getUrl();

    if (!$url) {
      return [];
    }

    return [
      '#type' => 'link',
      '#title' => $this->t('Add service'),
      '#url' => $url,
      '#attributes' => ['class' => ['add-service-button', 'button']],
    ];
  }

  /**
   * Get Current route.
   *
   * @return \Drupal\Core\Url|false
   *   Returns URL object if url is found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  private function getUrl() {
    $current_group = $this->currentGroup->getGroupFromRoute();
    if (empty($current_group)) {
      return Url::fromRoute('hel_tpm_general.group.node.add.service');
    }

    $access = $this->groupNodeAddAccessService->hasCreateServiceAccess($current_group, 'group_node:service', $this->currentUser);
    if ($access instanceof AccessResultAllowed) {
      return Url::fromUserInput($this->buildPath($current_group, 'group_node:service'));
    }
    return FALSE;
  }

  /**
   * Build create link path.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group interface.
   * @param string $plugin_id
   *   Plugin id.
   *
   * @return string
   *   Path to group service creation.
   */
  private function buildPath(GroupInterface $group, string $plugin_id) {
    return sprintf('/group/%s/content/create/%s', $group->id(), $plugin_id);
  }

}
