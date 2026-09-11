<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupMembershipInterface;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\PermissionScopeInterface;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use Drupal\views\Entity\View;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Security tests for the "reactivate group member" views link field.
 *
 * The field handler is a views entity link field (it extends the same
 * LinkBase as core's EntityLink) that exposes a link to
 * hel_tpm_group.reactivate_group_member for a group membership row. Two
 * separate things therefore have to hold:
 *
 * 1. The route must be closed: only a user holding the group permission
 *    'reactivate group members' in the *membership's own* group may reach the
 *    reactivation form, and only for a blocked account that belongs to exactly
 *    one group.
 * 2. The link must not be disclosed: the handler must render nothing at all
 *    when the current user could not follow the link, so the existence of the
 *    reactivation route and of the blocked account is not leaked.
 *
 * Note on the current user: both ReactivateGroupMemberAccess and the handler's
 * link rendering evaluate the *current* user rather than an account passed in,
 * so every case below switches identity with setCurrentUser() instead of
 * handing an account to the access manager.
 *
 * @coversDefaultClass \Drupal\hel_tpm_group\Plugin\views\field\GroupAdminReactivateMemberLink
 *
 * @see \Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess
 * @see \Drupal\views\Plugin\views\field\EntityLink
 *
 * @group hel_tpm_group
 */
class GroupAdminReactivateMemberLinkSecurityTest extends GroupKernelTestBase {

  /**
   * The views field plugin ID under test.
   */
  const PLUGIN_ID = 'hel_tpm_group_reactivate_group_user_link';

  /**
   * The reactivation route.
   */
  const ROUTE = 'hel_tpm_group.reactivate_group_member';

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'hel_tpm_mail_tools',
    'hel_tpm_group',
    'group',
    'ggroup',
    'message',
    'message_notify',
    'views',
  ];

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * The group type used by every test.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupType;

  /**
   * Individual group role that grants the reactivation permission.
   *
   * @var \Drupal\group\Entity\GroupRoleInterface
   */
  protected GroupRoleInterface $groupAdminRole;

  /**
   * Individual group role without any relevant permission.
   *
   * @var \Drupal\group\Entity\GroupRoleInterface
   */
  protected GroupRoleInterface $plainMemberRole;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->accessManager = $this->container->get('access_manager');
    $this->groupType = $this->createGroupType([
      'id' => 'reactivate_test',
      'creator_membership' => FALSE,
    ]);

    $this->groupAdminRole = $this->createGroupRole([
      'id' => 'reactivate_test-admin',
      'group_type' => $this->groupType->id(),
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['reactivate group members'],
    ]);

    $this->plainMemberRole = $this->createGroupRole([
      'id' => 'reactivate_test-member',
      'group_type' => $this->groupType->id(),
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => [],
    ]);
  }

  /**
   * The column is hidden when the view was not given a group argument.
   *
   * Without an argument the handler cannot know which group's permissions to
   * check, so it must fail closed rather than fall back to "no restriction".
   *
   * @covers ::access
   */
  public function testFieldAccessDeniedWithoutGroupArgument(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $admin = $this->createUser();
    $group->addMember($admin, ['group_roles' => [$this->groupAdminRole->id()]]);

    $handler = $this->createHandler([]);

    $this->assertTrue($handler->access($admin)->isForbidden());
  }

  /**
   * The column is hidden when the group argument points at a missing group.
   *
   * @covers ::access
   */
  public function testFieldAccessDeniedForUnknownGroup(): void {
    $admin = $this->createUser();
    $handler = $this->createHandler(['999999']);

    $this->assertTrue($handler->access($admin)->isForbidden());
  }

  /**
   * The column is hidden from a member without the group permission.
   *
   * @covers ::access
   */
  public function testFieldAccessDeniedForPlainMember(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $member = $this->createUser();
    $group->addMember($member, ['group_roles' => [$this->plainMemberRole->id()]]);

    $handler = $this->createHandler([(string) $group->id()]);

    $this->assertTrue($handler->access($member)->isForbidden());
  }

  /**
   * The column is hidden from a non-member, even a site user administrator.
   *
   * Global user administration permissions must not stand in for the group
   * permission.
   *
   * @covers ::access
   */
  public function testFieldAccessDeniedForNonMemberUserAdmin(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $outsider = $this->createUser(['administer users', 'administer permissions']);
    $this->createGroupRole([
      'group_type' => $this->groupType->id(),
      'scope' => PermissionScopeInterface::OUTSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
    ]);

    $handler = $this->createHandler([(string) $group->id()]);

    $this->assertTrue($handler->access($outsider)->isForbidden());
  }

  /**
   * The column is shown to a member holding the group permission.
   *
   * @covers ::access
   */
  public function testFieldAccessAllowedForGroupAdmin(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $admin = $this->createUser();
    $group->addMember($admin, ['group_roles' => [$this->groupAdminRole->id()]]);

    $handler = $this->createHandler([(string) $group->id()]);

    $this->assertTrue($handler->access($admin)->isAllowed());
  }

  /**
   * A group admin of another group cannot see the column for this group.
   *
   * Holding 'reactivate group members' somewhere must not carry over into
   * groups where the user holds nothing.
   *
   * @covers ::access
   */
  public function testFieldAccessDeniedForForeignGroupAdmin(): void {
    $ownGroup = $this->createGroup(['type' => $this->groupType->id()]);
    $otherGroup = $this->createGroup(['type' => $this->groupType->id()]);
    $foreignAdmin = $this->createUser();
    $otherGroup->addMember($foreignAdmin, ['group_roles' => [$this->groupAdminRole->id()]]);

    $handler = $this->createHandler([(string) $ownGroup->id()]);

    $this->assertTrue($handler->access($foreignAdmin)->isForbidden());
  }

  /**
   * No link is rendered for a member without the group permission.
   *
   * @covers ::render
   */
  public function testLinkNotRenderedForPlainMember(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $membership = $this->addMember($group, $this->createBlockedUser());

    $member = $this->createUser();
    $group->addMember($member, ['group_roles' => [$this->plainMemberRole->id()]]);
    $this->setCurrentUser($member);

    $this->assertSame([], $this->createHandler([(string) $group->id()])->render(new ResultRow(['_entity' => $membership])));
  }

  /**
   * No link is rendered for a group admin of a different group.
   *
   * This is the cross-group escalation case: the row belongs to group A while
   * the current user only administers group B.
   *
   * @covers ::render
   */
  public function testLinkNotRenderedForForeignGroupAdmin(): void {
    $groupA = $this->createGroup(['type' => $this->groupType->id()]);
    $groupB = $this->createGroup(['type' => $this->groupType->id()]);
    $membership = $this->addMember($groupA, $this->createBlockedUser());

    $foreignAdmin = $this->createUser();
    $groupB->addMember($foreignAdmin, ['group_roles' => [$this->groupAdminRole->id()]]);
    $this->setCurrentUser($foreignAdmin);

    // The view is even rendered with group B as its argument, so the field
    // level access check would pass; the row level check has to stop it.
    $this->assertSame([], $this->createHandler([(string) $groupB->id()])->render(new ResultRow(['_entity' => $membership])));
  }

  /**
   * No link is rendered for a member whose account is still active.
   *
   * Reactivation only makes sense for a blocked account, and offering it for an
   * active one would let a group admin touch a live account.
   *
   * @covers ::render
   */
  public function testLinkNotRenderedForActiveMember(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $activeMember = $this->createUser();
    $membership = $this->addMember($group, $activeMember);

    $this->setCurrentUser($this->createGroupAdmin($group));

    $this->assertSame([], $this->createHandler([(string) $group->id()])->render(new ResultRow(['_entity' => $membership])));
  }

  /**
   * No link is rendered when the blocked member belongs to several groups.
   *
   * A single group's admin must not be able to reactivate an account that
   * other groups also own.
   *
   * @covers ::render
   */
  public function testLinkNotRenderedForMemberOfMultipleGroups(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $otherGroup = $this->createGroup(['type' => $this->groupType->id()]);

    $blocked = $this->createBlockedUser();
    $membership = $this->addMember($group, $blocked);
    $this->addMember($otherGroup, $blocked);

    $this->setCurrentUser($this->createGroupAdmin($group));

    $this->assertSame([], $this->createHandler([(string) $group->id()])->render(new ResultRow(['_entity' => $membership])));
  }

  /**
   * The link is rendered for an authorised group admin.
   *
   * Counterpart to the negative cases: proves they fail for the right reason
   * and not because the handler never renders anything.
   *
   * @covers ::render
   */
  public function testLinkRenderedForAuthorisedGroupAdmin(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $membership = $this->addMember($group, $this->createBlockedUser());

    $this->setCurrentUser($this->createGroupAdmin($group));

    $build = $this->createHandler([(string) $group->id()])->render(new ResultRow(['_entity' => $membership]));

    $this->assertNotEmpty($build);
    $this->assertSame('link', $build['#type']);
    $this->assertInstanceOf('Drupal\Core\Url', $build['#url']);
    $this->assertSame(self::ROUTE, $build['#url']->getRouteName());
    $this->assertSame([
      'group' => $group->id(),
      'group_content' => $membership->id(),
    ], $build['#url']->getRouteParameters());
  }

  /**
   * The route denies a member without the group permission.
   *
   * @see \Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess::access()
   */
  public function testRouteAccessDeniedForPlainMember(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $membership = $this->addMember($group, $this->createBlockedUser());

    $member = $this->createUser();
    $group->addMember($member, ['group_roles' => [$this->plainMemberRole->id()]]);
    $this->setCurrentUser($member);

    $this->assertFalse($this->checkRouteAccess($membership));
  }

  /**
   * The route denies an authenticated non-member.
   *
   * @see \Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess::access()
   */
  public function testRouteAccessDeniedForOutsider(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $membership = $this->addMember($group, $this->createBlockedUser());

    $this->createGroupRole([
      'group_type' => $this->groupType->id(),
      'scope' => PermissionScopeInterface::OUTSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
    ]);
    $this->setCurrentUser($this->createUser(['administer users']));

    $this->assertFalse($this->checkRouteAccess($membership));
  }

  /**
   * The route denies the anonymous user.
   *
   * @see \Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess::access()
   */
  public function testRouteAccessDeniedForAnonymous(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $membership = $this->addMember($group, $this->createBlockedUser());

    $this->createGroupRole([
      'group_type' => $this->groupType->id(),
      'scope' => PermissionScopeInterface::OUTSIDER_ID,
      'global_role' => RoleInterface::ANONYMOUS_ID,
    ]);
    $this->setCurrentUser(new AnonymousUserSession());

    $this->assertFalse($this->checkRouteAccess($membership));
  }

  /**
   * The route denies a group admin of an unrelated group.
   *
   * @see \Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess::access()
   */
  public function testRouteAccessDeniedForForeignGroupAdmin(): void {
    $groupA = $this->createGroup(['type' => $this->groupType->id()]);
    $groupB = $this->createGroup(['type' => $this->groupType->id()]);
    $membership = $this->addMember($groupA, $this->createBlockedUser());

    $this->setCurrentUser($this->createGroupAdmin($groupB));

    $this->assertFalse($this->checkRouteAccess($membership));
  }

  /**
   * The route denies reactivating an account that is already active.
   *
   * @see \Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess::access()
   */
  public function testRouteAccessDeniedForActiveMember(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $membership = $this->addMember($group, $this->createUser());

    $this->setCurrentUser($this->createGroupAdmin($group));

    $this->assertFalse($this->checkRouteAccess($membership));
  }

  /**
   * The route denies reactivating an account owned by several groups.
   *
   * @see \Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess::access()
   */
  public function testRouteAccessDeniedForMemberOfMultipleGroups(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $otherGroup = $this->createGroup(['type' => $this->groupType->id()]);

    $blocked = $this->createBlockedUser();
    $membership = $this->addMember($group, $blocked);
    $this->addMember($otherGroup, $blocked);

    $this->setCurrentUser($this->createGroupAdmin($group));

    $this->assertFalse($this->checkRouteAccess($membership));
  }

  /**
   * The route allows an authorised group admin.
   *
   * @see \Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess::access()
   */
  public function testRouteAccessAllowedForGroupAdmin(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $membership = $this->addMember($group, $this->createBlockedUser());

    $this->setCurrentUser($this->createGroupAdmin($group));

    $this->assertTrue($this->checkRouteAccess($membership));
  }

  /**
   * Losing the group role immediately closes the route again.
   *
   * Guards against the permission being read once and then trusted.
   *
   * @see \Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess::access()
   */
  public function testRouteAccessRevokedWithGroupRole(): void {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $membership = $this->addMember($group, $this->createBlockedUser());

    $admin = $this->createGroupAdmin($group);
    $this->setCurrentUser($admin);
    $this->assertTrue($this->checkRouteAccess($membership));

    $adminMembership = GroupMembership::loadSingle($group, $admin);
    $adminMembership->set('group_roles', [$this->plainMemberRole->id()]);
    $adminMembership->save();

    $this->assertFalse($this->checkRouteAccess($membership));
  }

  /**
   * Checks access to the reactivation route for the current user.
   *
   * @param \Drupal\group\Entity\GroupMembershipInterface $membership
   *   The membership to be reactivated.
   *
   * @return bool
   *   TRUE if the current user may reach the reactivation form.
   */
  protected function checkRouteAccess(GroupMembershipInterface $membership): bool {
    return $this->accessManager->checkNamedRoute(self::ROUTE, [
      'group' => $membership->getGroupId(),
      'group_content' => $membership->id(),
    ]);
  }

  /**
   * Instantiates the views field handler with the given view arguments.
   *
   * @param string[] $args
   *   The view arguments; the first one is read as the group ID.
   *
   * @return \Drupal\hel_tpm_group\Plugin\views\field\GroupAdminReactivateMemberLink
   *   The handler.
   */
  protected function createHandler(array $args) {
    $handler = $this->container->get('plugin.manager.views.field')->createInstance(self::PLUGIN_ID);
    $handler->view = $this->createExecutable($args);
    return $handler;
  }

  /**
   * Builds a throwaway view executable carrying the given arguments.
   *
   * @param string[] $args
   *   The view arguments.
   *
   * @return \Drupal\views\ViewExecutable
   *   The executable.
   */
  protected function createExecutable(array $args): ViewExecutable {
    $view = View::create([
      'id' => 'hel_tpm_group_reactivate_link_test',
      'label' => 'Reactivate link test',
      'base_table' => 'group_relationship_field_data',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_title' => 'Default',
          'display_plugin' => 'default',
          'position' => 0,
          'display_options' => [],
        ],
      ],
    ]);

    $executable = $view->getExecutable();
    $executable->setArguments($args);
    return $executable;
  }

  /**
   * Creates a blocked user account.
   *
   * @return \Drupal\user\UserInterface
   *   The blocked user.
   */
  protected function createBlockedUser(): UserInterface {
    $user = $this->createUser();
    $user->block();
    $user->save();
    return $user;
  }

  /**
   * Adds a user to a group and returns the resulting membership.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   * @param \Drupal\user\UserInterface $user
   *   The user to add.
   *
   * @return \Drupal\group\Entity\GroupMembershipInterface
   *   The membership.
   */
  protected function addMember(GroupInterface $group, UserInterface $user): GroupMembershipInterface {
    $group->addMember($user);
    // Deliberately the bundle class loader rather than Group::getMember(),
    // which still returns the deprecated wrapper object instead of the
    // relationship entity the field handler and the route both operate on.
    return GroupMembership::loadSingle($group, $user);
  }

  /**
   * Creates a user holding the reactivation permission in a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to administer.
   *
   * @return \Drupal\user\UserInterface
   *   The group admin.
   */
  protected function createGroupAdmin(GroupInterface $group): UserInterface {
    $admin = $this->createUser();
    $group->addMember($admin, ['group_roles' => [$this->groupAdminRole->id()]]);
    return $admin;
  }

}
