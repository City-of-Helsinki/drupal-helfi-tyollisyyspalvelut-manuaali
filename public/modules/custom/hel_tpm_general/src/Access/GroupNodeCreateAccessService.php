<?php

namespace Drupal\hel_tpm_general\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\group\Access\GroupContentCreateEntityAccessCheck;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembershipLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

class GroupNodeCreateAccessService {

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
   * @param \Drupal\group\GroupMembershipLoader $group_membership_loader
   * @param \Drupal\group\Access\GroupContentCreateEntityAccessCheck $group_content_create_entity_access
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   * @param \Symfony\Component\Routing\RouterInterface $router
   */
  public function __construct(GroupMembershipLoader $group_membership_loader, GroupContentCreateEntityAccessCheck $group_content_create_entity_access, RouterInterface $router) {
    $this->groupMembershipLoader = $group_membership_loader;
    $this->groupContentCreateEntityAccess = $group_content_create_entity_access;
    $this->router = $router;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('group.membership_loader'),
      $container->get('access_check.group_content.create_entity'),
      $container->get('router.no_access_checks')
    );
  }

  /**
   * Access callback.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   */
  public function hasAccess(AccountInterface $account, $plugin_id) {
    $groups = $this->userGroups($account);

    foreach ($groups as $group) {
      if ($this->hasCreateServiceAccess($group, $plugin_id, $account)) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

  /**
   * @param \Drupal\group\Entity\GroupInterface $group
   * @param $plugin_id
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   */
  public function hasCreateServiceAccess(GroupInterface $group, $plugin_id, AccountInterface $account) {
    $path = $this->buildPath($group, $plugin_id);
    $route = $this->getRouteByPath($path);
    return $this->groupContentCreateEntityAccess->access($route, $account, $group, $plugin_id);
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
   * @param $path
   *
   * @return false|mixed
   */
  private function getRouteByPath($path) {
    $route = $this->router->match($path);
    if (empty($route)) {
      return FALSE;
    }
    return $route['_route_object'];
  }

  /**
   * @return array
   */
  protected function userGroups($account): array {
    $groups = [];
    $memberships = $this->groupMembershipLoader->loadByUser($account);
    if (empty($memberships)) {
      return $groups;
    }

    foreach ($memberships as $membership) {
      $groups[] = $membership->getGroup();
    }
    return $groups;
  }

}
