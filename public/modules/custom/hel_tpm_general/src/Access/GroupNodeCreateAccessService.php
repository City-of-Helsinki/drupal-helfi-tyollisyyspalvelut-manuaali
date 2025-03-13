<?php

namespace Drupal\hel_tpm_general\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Access\GroupRelationshipCreateEntityAccessCheck;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembershipLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Group node create access service.
 */
class GroupNodeCreateAccessService implements ContainerInjectionInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\group\GroupMembershipLoader $groupMembershipLoader
   *   Group membership loader service.
   * @param \Drupal\group\Access\GroupRelationshipCreateEntityAccessCheck $groupContentCreateEntityAccess
   *   Group content create entity access service.
   * @param \Symfony\Component\Routing\RouterInterface $router
   *   Router service.
   */
  public function __construct(
    protected GroupMembershipLoader $groupMembershipLoader,
    protected GroupRelationshipCreateEntityAccessCheck $groupContentCreateEntityAccess,
    protected RouterInterface $router,
  ) {}

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
   *   Access result interface.
   */
  public function hasAccess(AccountInterface $account, $plugin_id) {
    $groups = $this->userGroups($account);

    foreach ($groups as $group) {
      $access = $this->hasCreateServiceAccess($group, $plugin_id, $account);
      if ($access instanceof AccessResultAllowed) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

  /**
   * Create service access checker.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group interface.
   * @param string $plugin_id
   *   Plugin id.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account interface.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Access result.
   */
  public function hasCreateServiceAccess(GroupInterface $group, string $plugin_id, AccountInterface $account): AccessResultInterface {
    $path = $this->buildPath($group, $plugin_id);
    $route = $this->getRouteByPath($path);
    return $this->groupContentCreateEntityAccess->access($route, $account, $group, $plugin_id);
  }

  /**
   * Build link path for group content creation.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group interface.
   * @param string $plugin_id
   *   Plugin id.
   *
   * @return string
   *   Link path for group content creation.
   */
  private function buildPath(GroupInterface $group, string $plugin_id): string {
    return sprintf('/group/%s/content/create/%s', $group->id(), $plugin_id);
  }

  /**
   * Get route by path.
   *
   * @param string $path
   *   Path string.
   *
   * @return false|mixed
   *   If route is found return route object.
   */
  private function getRouteByPath(string $path): mixed {
    $route = $this->router->match($path);
    if (empty($route)) {
      return FALSE;
    }
    return $route['_route_object'];
  }

  /**
   * User groups for selected user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account interface.
   *
   * @return array
   *   Groups.
   */
  protected function userGroups(AccountInterface $account): array {
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
