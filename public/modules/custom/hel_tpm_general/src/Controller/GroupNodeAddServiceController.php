<?php

namespace Drupal\hel_tpm_general\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\group\Access\GroupContentCreateEntityAccessCheck;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembershipLoader;
use Drupal\hel_tpm_general\Access\GroupNodeCreateAccessService;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

class GroupNodeAddServiceController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * @var \Drupal\group\GroupMembershipLoader
   */
  protected $groupMembershipLoader;

  /**
   * @var \Drupal\group\Access\GroupContentCreateEntityAccessCheck
   */
  protected $groupContentCreateEntityAccess;

  /**
   * @var \Symfony\Component\Routing\RouterInterface
   */
  protected $router;

  /**
   * @var string
   */
  protected $plugin_id = 'group_node:service';

  protected $user;

  /**
   * @var \Drupal\hel_tpm_general\Access\GroupNodeCreateAccessService
   */
  private $groupNodeCreateAccessService;

  /**
   * @param \Drupal\group\GroupMembershipLoader $group_membership_loader
   * @param \Drupal\group\Access\GroupContentCreateEntityAccessCheck $group_content_create_entity_access
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   * @param \Symfony\Component\Routing\RouterInterface $router
   */
  public function __construct(GroupMembershipLoader $group_membership_loader, RouterInterface $router, GroupNodeCreateAccessService $group_node_create_access_service, AccountProxyInterface $user) {
    $this->groupMembershipLoader = $group_membership_loader;
    $this->router = $router;
    $this->groupNodeCreateAccessService = $group_node_create_access_service;
    $this->user = $user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('group.membership_loader'),
      $container->get('router.no_access_checks'),
      $container->get('hel_tpm_general.group_node_add_access_service'),
      $container->get('current_user')
    );
  }

  /**
   * Access callback.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   */
  public function access() {
    return $this->groupNodeCreateAccessService->hasAccess($this->currentUser(), $this->plugin_id);
  }

  /**
   * Title callback.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function title() {
    return $this->t('Create service for group');
  }

  /**
   * Add service list method.
   *
   * @return array
   */
  public function addServiceList() : array {
    $groups = $this->userGroups();
    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Select group'),
      '#items' => $this->generateLinks($groups)
    ];
  }

  /**
   * Group selection callback when creating a new service.
   *
   * @param $groups
   *
   * @return array
   */
  private function generateLinks($groups) {
    $links = [];
    if (empty($groups)) {
      return $links;
    }
    foreach ($groups as $group) {
      if (!$this->hasCurrentUserCreateServiceAccess($group)) {
        continue;
      }
      $links[] = [
        '#type' => 'link',
        '#title' => $group->label(),
        '#url' => Url::fromUserInput($this->buildPath($group, $this->plugin_id))
      ];
    }

    return $links;
  }

  /**
   * @param \Drupal\group\Entity\GroupInterface $group
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   */
  public function hasCurrentUserCreateServiceAccess(GroupInterface $group) {
    return $this->groupNodeCreateAccessService->hasCreateServiceAccess($group, $this->plugin_id, $this->user);
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

  /**
   * @return array
   */
  protected function userGroups() : array {
    $groups = [];
    $memberships = $this->groupMembershipLoader->loadByUser($this->currentUser);
    if (empty($memberships)) {
      return $groups;
    }

    foreach ($memberships as $membership) {
      $groups[] = $membership->getGroup();
    }
    return $groups;
  }
}