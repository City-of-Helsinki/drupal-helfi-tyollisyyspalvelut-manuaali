<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupMembershipInterface;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\group\PermissionScopeInterface;
use Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess;
use Drupal\hel_tpm_group\Form\ReactivateGroupMemberForm;
use Drupal\user\UserInterface;
use Drupal\views\Entity\View;
use Drupal\views\ResultRow;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Tests group member reactivation functionality and access control.
 *
 * @group hel_tpm_group
 * @coversDefaultClass \Drupal\hel_tpm_group\Form\ReactivateGroupMemberForm
 * @covers \Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess
 * @covers \Drupal\hel_tpm_group\Plugin\views\field\GroupAdminReactivateMemberLink
 */
final class ReactivateGroupMemberFormTest extends GroupKernelTestBase {

  /**
   * {@inheritdoc}
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
   * The group type used by the tests.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  private GroupTypeInterface $groupType;

  /**
   * The group role allowed to reactivate members.
   *
   * @var \Drupal\group\Entity\GroupRoleInterface
   */
  private GroupRoleInterface $adminRole;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->groupType = $this->createGroupType([
      'id' => 'reactivate_test',
      'creator_membership' => FALSE,
    ]);
    $this->adminRole = $this->createGroupRole([
      'id' => 'reactivate_test-admin',
      'group_type' => $this->groupType->id(),
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['reactivate group members'],
    ]);
  }

  /**
   * Tests that users without access cannot build the form.
   *
   * @covers ::buildForm
   */
  public function testBuildFormRequiresAccess(): void {
    [$membership] = $this->createMembership();
    $this->setCurrentUser($this->createUser());

    $this->assertSame([], $this->createForm($membership)->buildForm([], new FormState()));
  }

  /**
   * Tests that an administrator cannot reactivate another group's member.
   *
   * @covers ::buildForm
   */
  public function testForeignGroupAdminCannotBuildForm(): void {
    [$membership] = $this->createMembership();
    $other_group = $this->createGroup(['type' => $this->groupType->id()]);
    $this->setCurrentUser($this->createAdmin($other_group));

    $this->assertSame([], $this->createForm($membership)->buildForm([], new FormState()));
  }

  /**
   * Tests that an active account cannot be reactivated.
   *
   * @covers ::buildForm
   */
  public function testActiveMemberCannotBeReactivated(): void {
    [$membership, $group, $member] = $this->createMembership();
    $member->activate()->save();
    $this->setCurrentUser($this->createAdmin($group));

    $this->assertSame([], $this->createForm($membership)->buildForm([], new FormState()));
  }

  /**
   * Tests that a member shared by multiple groups cannot be reactivated.
   *
   * @covers ::buildForm
   */
  public function testMemberOfMultipleGroupsCannotBeReactivated(): void {
    [$membership, $group, $member] = $this->createMembership();
    $other_group = $this->createGroup(['type' => $this->groupType->id()]);
    $other_group->addMember($member);
    $this->setCurrentUser($this->createAdmin($group));

    $this->assertSame([], $this->createForm($membership)->buildForm([], new FormState()));
  }

  /**
   * Tests that validation repeats the access check.
   *
   * @covers ::validateForm
   */
  public function testValidationRechecksAccess(): void {
    [$membership, $group] = $this->createMembership();
    $admin = $this->createUser();
    $group->addMember($admin, ['group_roles' => [$this->adminRole->id()]]);
    $this->setCurrentUser($admin);
    $form = $this->createForm($membership);

    GroupMembership::loadSingle($group, $admin)->delete();
    $form_state = new FormState();
    $build = [];
    $form->validateForm($build, $form_state);

    $this->assertTrue($form_state->hasAnyErrors());
  }

  /**
   * Tests that an authorised submission activates the member.
   *
   * @covers ::buildForm
   * @covers ::submitForm
   */
  public function testAuthorisedSubmissionActivatesMember(): void {
    [$membership, $group, $blocked] = $this->createMembership();
    $admin = $this->createUser();
    $group->addMember($admin, ['group_roles' => [$this->adminRole->id()]]);
    $this->setCurrentUser($admin);
    $form = $this->createForm($membership);

    $build = $form->buildForm([], new FormState());
    $this->assertSame('submit', $build['button']['#type']);
    $form->submitForm($build, new FormState());

    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $this->assertTrue($storage->loadUnchanged($blocked->id())->isActive());
  }

  /**
   * Tests access-service functionality and fail-closed security paths.
   */
  public function testAccessService(): void {
    [$membership, $group, $blocked] = $this->createMembership();
    $admin = $this->createAdmin($group);
    $this->setCurrentUser($admin);
    $access = $this->createAccess($membership);

    $this->assertInstanceOf(ReactivateGroupMemberAccess::class, ReactivateGroupMemberAccess::create($this->container));
    $this->assertTrue($access->access()->isAllowed());
    $this->assertTrue($access->access($membership)->isAllowed());
    $this->assertTrue($access->canActivateGroupMembers($admin));
    $this->assertTrue($access->canActivateGroupMembers($admin, $group));

    $blocked->activate()->save();
    $this->assertTrue($access->access($membership)->isForbidden());
    $blocked->block()->save();

    $other_group = $this->createGroup(['type' => $this->groupType->id()]);
    $other_group->addMember($blocked);
    $this->assertTrue($access->access($membership)->isForbidden());

    $this->setCurrentUser(new AnonymousUserSession());
    $this->assertFalse($access->canActivateGroupMembers($this->container->get('current_user'), $group));

    $missing_group = $this->createMock(GroupMembershipInterface::class);
    $missing_group->method('getGroup')->willReturn(NULL);
    $this->assertTrue($access->access($missing_group)->isForbidden());
    $this->assertFalse($this->createAccess($missing_group)->canActivateGroupMembers($admin));

    $not_membership = $this->createMock(GroupMembershipInterface::class);
    $not_membership->method('getGroup')->willReturn($group);
    $this->assertTrue($access->access($not_membership)->isForbidden());

    $anonymous = $this->createMock(UserInterface::class);
    $anonymous->method('isAnonymous')->willReturn(TRUE);
    $anonymous_membership = $this->createMock(GroupMembership::class);
    $anonymous_membership->method('getGroup')->willReturn($group);
    $anonymous_membership->method('getEntity')->willReturn($anonymous);
    $this->setCurrentUser($admin);
    $this->assertTrue($access->access($anonymous_membership)->isForbidden());
  }

  /**
   * Tests field access, rendering, routing, and information disclosure.
   */
  public function testReactivationLink(): void {
    [$membership, $group] = $this->createMembership();
    $admin = $this->createAdmin($group);
    $handler = $this->createHandler([]);

    $this->assertTrue($handler->access($admin)->isForbidden());
    $handler = $this->createHandler(['999999']);
    $this->assertTrue($handler->access($admin)->isForbidden());
    $handler = $this->createHandler([(string) $group->id()]);
    $this->assertTrue($handler->access($this->createUser())->isForbidden());
    $this->assertTrue($handler->access($admin)->isAllowed());

    $this->setCurrentUser($admin);
    $build = $handler->render(new ResultRow(['_entity' => $membership]));
    $this->assertSame('Reactivate', (string) $build['#title']);
    $this->assertSame('hel_tpm_group.reactivate_group_member', $build['#url']->getRouteName());
    $this->assertSame(['destination' => '/'], $build['#url']->getOption('query'));

    $this->setCurrentUser($this->createUser());
    $this->assertSame([], $handler->render(new ResultRow(['_entity' => $membership])));

    $form = [];
    $handler->buildOptionsForm($form, new FormState());
    $handler->query();
    $this->assertIsArray($form);
  }

  /**
   * Creates a blocked user and adds them to a new group.
   *
   * @return array
   *   The membership, group, and blocked user.
   */
  private function createMembership(): array {
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $blocked = $this->createUser();
    $blocked->block()->save();
    $group->addMember($blocked);
    return [GroupMembership::loadSingle($group, $blocked), $group, $blocked];
  }

  /**
   * Creates an administrator for a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to administer.
   *
   * @return \Drupal\user\UserInterface
   *   The administrator account.
   */
  private function createAdmin(GroupInterface $group): UserInterface {
    $admin = $this->createUser();
    $group->addMember($admin, ['group_roles' => [$this->adminRole->id()]]);
    return $admin;
  }

  /**
   * Creates the reactivation form under test.
   *
   * @param \Drupal\group\Entity\GroupMembershipInterface $membership
   *   The membership to reactivate.
   *
   * @return \Drupal\hel_tpm_group\Form\ReactivateGroupMemberForm
   *   The reactivation form.
   */
  private function createForm(GroupMembershipInterface $membership): ReactivateGroupMemberForm {
    return new ReactivateGroupMemberForm($this->createRouteMatch($membership), $this->createAccess($membership));
  }

  /**
   * Creates the reactivation access checker.
   *
   * @param \Drupal\group\Entity\GroupMembershipInterface $membership
   *   The membership supplied through the route.
   *
   * @return \Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess
   *   The access checker.
   */
  private function createAccess(GroupMembershipInterface $membership): ReactivateGroupMemberAccess {
    return new ReactivateGroupMemberAccess($this->container->get('current_user'), $this->createRouteMatch($membership));
  }

  /**
   * Creates a route match for a membership.
   *
   * @param \Drupal\group\Entity\GroupMembershipInterface $membership
   *   The membership supplied through the route.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The mocked route match.
   */
  private function createRouteMatch(GroupMembershipInterface $membership): RouteMatchInterface {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getParameter')->willReturn($membership);
    $route_match->method('getParameters')->willReturn(new ParameterBag(['group_content' => $membership]));
    return $route_match;
  }

  /**
   * Creates the reactivation Views field handler.
   *
   * @param string[] $args
   *   The view arguments.
   *
   * @return \Drupal\hel_tpm_group\Plugin\views\field\GroupAdminReactivateMemberLink
   *   The configured field handler.
   */
  private function createHandler(array $args) {
    $handler = $this->container->get('plugin.manager.views.field')->createInstance('hel_tpm_group_reactivate_group_user_link');
    $view = View::create([
      'id' => 'reactivate_link_test',
      'label' => 'Reactivate link test',
      'base_table' => 'group_relationship_field_data',
    ]);
    $handler->view = $view->getExecutable();
    $handler->view->setArguments($args);
    return $handler;
  }

}
