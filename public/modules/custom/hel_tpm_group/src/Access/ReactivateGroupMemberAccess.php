<?php

namespace Drupal\hel_tpm_group\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupMembershipInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides access control for reactivating group members.
 *
 * This class determines whether a user has sufficient permissions to access
 * the form used to reactivate group members.
 */
class ReactivateGroupMemberAccess implements ContainerInjectionInterface {

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private AccountProxyInterface $currentUser;

  /**
   * Route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  private RouteMatchInterface $routeMatch;

  /**
   * Class constructor.
   *
   * Initializes the object with the current user and route match dependencies.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user proxy service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @return void
   *   This method does not return a value.
   */
  public function __construct(
    AccountProxyInterface $current_user,
    RouteMatchInterface $route_match,
  ) {
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
  }

  /**
   * Creates a new instance of the class using the container.
   *
   * This method instantiates the class with dependencies retrieved
   * from the provided container.
   *
   * @param \Psr\Container\ContainerInterface $container
   *   The service container used to retrieve dependencies.
   *
   * @return static
   *   A new instance of the class.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('current_route_match')
    );
  }

  /**
   * Checks access for reactivating a group member.
   *
   * Validates whether the current user has permission and satisfies all
   * conditions to reactivate a group membership.
   *
   * @param \Drupal\group\Entity\GroupMembershipInterface|null $group_content
   *   The group membership instance, or NULL if not provided.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object determining if the operation is allowed.
   */
  public function access(?GroupMembershipInterface $group_content = NULL): AccessResultInterface {
    if (empty($group_content)) {
      $params = $this->routeMatch->getParameters()->all();
      $group_content = $params['group_content'];
    }

    $group = $group_content->getGroup();
    if (empty($group)) {
      return AccessResult::forbidden();
    }

    // Check that the group content is a group membership.
    if (!$group_content instanceof GroupMembership) {
      return AccessResult::forbidden();
    }

    // Make sure the user has permission to reactivate group members.
    if (!$group->hasPermission('reactivate group members', $this->currentUser)) {
      return AccessResult::forbidden();
    }

    $user = $group_content->getEntity();
    // Don't allow account activation for anonymous or active users.
    if ($user->isActive() || $user->isAnonymous()) {
      return AccessResult::forbidden();
    }

    $memberships = $this->getUserMemberships($user);

    // Prevent account activation if user belongs to multiple groups.
    if (count($memberships) > 1) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  /**
   * Checks if a user can reactivate group members.
   *
   * Determines if the given account has the 'reactivate group members'
   * permission within the specified group or the default group.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check permissions for.
   * @param \Drupal\group\Entity\GroupInterface|null $group
   *   (optional) The group entity to check permissions in. If NULL,
   *   retrieves the default group.
   *
   * @return bool
   *   TRUE if the user has the 'reactivate group members' permission;
   *   otherwise FALSE.
   */
  public function canActivateGroupMembers(AccountInterface $account, $group = NULL): bool {
    if (empty($group)) {
      $group = $this->getGroup();
    }
    if (empty($group)) {
      return FALSE;
    }
    return $group->hasPermission('reactivate group members', $account);
  }

  /**
   * Retrieves the group from the route parameters.
   *
   * Extracts the group entity associated with the group_content parameter in
   * the current route, if available.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The group entity object if found, or NULL if no group is associated.
   */
  protected function getGroup() {
    $params = $this->routeMatch->getParameters()->all();
    $group_content = $params['group_content'];
    $group = $group_content->getGroup();
    if (empty($group)) {
      return NULL;
    }
    return $group;
  }

  /**
   * Retrieves the group memberships for a given user.
   *
   * Loads and returns the memberships associated with the specified user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account for which to load memberships.
   *
   * @return \Drupal\group\Entity\GroupMembership[]
   *   An array of group membership objects associated with the user.
   */
  protected function getUserMemberships(AccountInterface $user) {
    return GroupMembership::loadByUser($user);
  }

}
