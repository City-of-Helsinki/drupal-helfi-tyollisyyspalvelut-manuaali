<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupMembershipInterface;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\PermissionScopeInterface;
use Drupal\hel_tpm_group\Plugin\views\field\GroupAdminReactivateMemberLink;
use Drupal\user\UserInterface;
use Drupal\views\Entity\View;
use Drupal\views\ResultRow;

/**
 * Tests the group member reactivation Views field.
 *
 * @group hel_tpm_group
 * @coversDefaultClass \Drupal\hel_tpm_group\Plugin\views\field\GroupAdminReactivateMemberLink
 */
final class GroupAdminReactivateMemberLinkTest extends GroupKernelTestBase {

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
   * Represents the role assigned to an administrator.
   *
   * @var \Drupal\group\Entity\GroupRoleInterface
   */
  private GroupRoleInterface $adminRole;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['views']);
    $group_type = $this->createGroupType([
      'id' => 'reactivate_link_test',
      'creator_membership' => FALSE,
    ]);
    $this->adminRole = $this->createGroupRole([
      'id' => 'reactivate_link_test-admin',
      'group_type' => $group_type->id(),
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => ['reactivate group members'],
    ]);
  }

  /**
   * Tests plugin creation and the Views integration methods.
   *
   * @covers ::create
   * @covers ::__construct
   * @covers ::buildOptionsForm
   * @covers ::query
   * @covers ::getUrlInfo
   */
  public function testPluginIntegration(): void {
    $handler = $this->createHandler([]);
    $this->assertInstanceOf(GroupAdminReactivateMemberLink::class, $handler);

    // Views normally merges these values in from hook_views_data(). Direct
    // plugin-manager construction in this kernel test does not.
    $definition = new \ReflectionProperty($handler, 'definition');
    $definition->setValue($handler, [
      'title' => 'Reactivate member',
      'group' => 'Group',
    ] + $definition->getValue($handler));

    $form = [
      'alter' => [
        'path' => [],
        'query' => [],
        'external' => [],
      ],
    ];
    $handler->buildOptionsForm($form, new FormState());
    $handler->query();

    $method = new \ReflectionMethod($handler, 'getUrlInfo');
    $this->assertNull($method->invoke($handler, new ResultRow()));
    $this->assertIsArray($form);
  }

  /**
   * Tests that field-level access fails closed and is group-specific.
   *
   * @covers ::access
   * @covers ::getGroup
   */
  public function testFieldAccess(): void {
    $group = $this->createGroup(['type' => 'reactivate_link_test']);
    $other_group = $this->createGroup(['type' => 'reactivate_link_test']);
    $admin = $this->createAdmin($group);
    $outsider = $this->createUser(['administer users']);
    $foreign_admin = $this->createAdmin($other_group);

    $this->assertTrue($this->createHandler([])->access($admin)->isForbidden());
    $this->assertTrue($this->createHandler(['999999'])->access($admin)->isForbidden());

    $handler = $this->createHandler([(string) $group->id()]);
    $this->assertTrue($handler->access(new AnonymousUserSession())->isForbidden());
    $this->assertTrue($handler->access($outsider)->isForbidden());
    $this->assertTrue($handler->access($foreign_admin)->isForbidden());
    $this->assertTrue($handler->access($admin)->isAllowed());
  }

  /**
   * Tests the link route, parameters, title, and destination.
   *
   * @covers ::render
   * @covers ::createReactivationLink
   */
  public function testRenderedLink(): void {
    [$membership, $group] = $this->createBlockedMembership();
    $this->setCurrentUser($this->createAdmin($group));
    $this->container->get('path.current')->setPath('/group/members');

    $build = $this->createHandler([(string) $group->id()])
      ->render(new ResultRow(['_entity' => $membership]));

    $this->assertSame('link', $build['#type']);
    $this->assertSame('Reactivate', (string) $build['#title']);
    $this->assertSame('hel_tpm_group.reactivate_group_member', $build['#url']->getRouteName());
    $this->assertSame([
      'group' => $group->id(),
      'group_content' => $membership->id(),
    ], $build['#url']->getRouteParameters());
    $this->assertSame(['destination' => '/group/members'], $build['#url']->getOption('query'));
  }

  /**
   * Tests that inaccessible rows never disclose a reactivation link.
   *
   * @covers ::render
   * @covers ::createReactivationLink
   */
  public function testLinkSecurity(): void {
    [$membership, $group, $blocked] = $this->createBlockedMembership();
    $other_group = $this->createGroup(['type' => 'reactivate_link_test']);

    $this->setCurrentUser($this->createUser());
    $this->assertSame([], $this->renderMembership($membership, $group));

    $this->setCurrentUser($this->createAdmin($other_group));
    $this->assertSame([], $this->renderMembership($membership, $other_group));

    $this->setCurrentUser($this->createAdmin($group));
    $blocked->activate()->save();
    $this->assertSame([], $this->renderMembership($membership, $group));

    $blocked->block()->save();
    $other_group->addMember($blocked);
    $this->assertSame([], $this->renderMembership($membership, $group));
  }

  /**
   * Creates a configured field handler.
   */
  private function createHandler(array $args): GroupAdminReactivateMemberLink {
    $handler = $this->container->get('plugin.manager.views.field')
      ->createInstance('hel_tpm_group_reactivate_group_user_link');
    $view = View::create([
      'id' => 'reactivate_link_test',
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
    $executable->setDisplay('default');
    $options = [];
    $handler->init($executable, $executable->display_handler, $options);
    $handler->view = $executable;
    $handler->view->setArguments($args);
    return $handler;
  }

  /**
   * Creates a blocked group membership.
   */
  private function createBlockedMembership(): array {
    $group = $this->createGroup(['type' => 'reactivate_link_test']);
    $blocked = $this->createUser();
    $blocked->block()->save();
    $group->addMember($blocked);
    return [GroupMembership::loadSingle($group, $blocked), $group, $blocked];
  }

  /**
   * Creates a group administrator.
   */
  private function createAdmin(GroupInterface $group): UserInterface {
    $admin = $this->createUser();
    $group->addMember($admin, ['group_roles' => [$this->adminRole->id()]]);
    return $admin;
  }

  /**
   * Renders a membership row.
   */
  private function renderMembership(GroupMembershipInterface $membership, GroupInterface $view_group): array {
    return $this->createHandler([(string) $view_group->id()])
      ->render(new ResultRow(['_entity' => $membership]));
  }

}
