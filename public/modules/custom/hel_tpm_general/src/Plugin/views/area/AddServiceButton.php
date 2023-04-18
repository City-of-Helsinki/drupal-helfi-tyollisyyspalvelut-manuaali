<?php

namespace Drupal\hel_tpm_general\Plugin\views\area;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\hel_tpm_general\Access\GroupNodeCreateAccessService;
use Drupal\hel_tpm_general\Controller\GroupNodeAddServiceController;
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
  private $groupNodeAddAccessService;

  /**
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\hel_tpm_general\Access\GroupNodeCreateAccessService $groupNodeCreateAccessService
   * @param \Drupal\hel_tpm_group\CurrentGroup $currentGroup
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GroupNodeCreateAccessService $groupNodeCreateAccessService, CurrentGroup $currentGroup, AccountProxyInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->groupNodeAddAccessService = $groupNodeCreateAccessService;
    $this->currentGroup = $currentGroup;
    $this->currentUser = $currentUser;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   *
   * @return \Drupal\hel_tpm_general\Plugin\views\area\AddServiceButton|\Drupal\views\Plugin\views\PluginBase|static
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
   *
   * @return bool
   */
  public function access(AccountInterface $account) {
    $access = $this->groupNodeAddAccessService->hasAccess($account, 'group_node:service');
    if ($access instanceof AccessResultAllowed) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * @param $empty
   *
   * @return array
   */
  public function render($empty = FALSE) {
    return $this->addServiceGroupListLink();
  }

  /**
   * @return array
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
      '#attributes' => ['class' => ['add-service-button', 'button']]
    ];
  }

  /**
   * @return \Drupal\Core\Url|false
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
   * @param $group
   * @param $plugin_id
   *
   * @return string
   */
  private function buildPath($group, $plugin_id) {
    return sprintf('/group/%s/content/create/%s', $group->id(), $plugin_id);
  }
}
