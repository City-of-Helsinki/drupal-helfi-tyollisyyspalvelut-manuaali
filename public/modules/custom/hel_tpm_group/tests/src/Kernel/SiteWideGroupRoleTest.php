<?php

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\group\Functional\GroupBrowserTestBase;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;

/**
 * Test description.
 *
 * @group hel_tpm_group
 */
class SiteWideGroupRoleTest extends GroupKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'hel_tpm_group',
    'group',
    'hook_post_action',
    'options',
    'entity',
    'variationcache',
    'group_test_config',
  ];

  /**
   * Group role storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $groupRoleStorage;

  /**
   * User account.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $account;

  /**
   * Group object.
   *
   * @var \Drupal\group\Entity\Group
   */
  protected $group;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->groupRoleStorage = $this->entityTypeManager->getStorage('group_role');
    $this->groupRoleSynchronizer = $this->container->get('group_role.synchronizer');

    $container = new ContainerBuilder();

    $site_wide_role_changed = $this->getMockBuilder('Drupal\hel_tpm_group\EventSubscriber\HelTpmGroupSubscriber')
      ->disableOriginalConstructor()
      ->getMock();
    $container->set('hel_tpm_group.site_wide_role_changed', $site_wide_role_changed);

    $this->createRole([], 'siterole', 'Site role');
    $this->group = $this->createGroup();
  }

  public function testGroupSiteWideRoleChanged() {

  }
  /**
   * Test callback.
   */
  public function testSiteWideRole() {
    // Grant the user a new site role and check the storage.
    $this->entityTypeManager->getStorage('user_role')
      ->create(['id' => 'publisher', 'label' => 'Publisher'])
      ->save();

    $this->assertEqualsCanonicalizing(['authenticated'], $this->account->getRoles(), 'Account doesn\'t have any roles');
    $this->group->addMember($this->account);
    $this->assertEqualsCanonicalizing(['authenticated'], $this->account->getRoles(), 'Account doesn\'t have any sitewide roles after adding to group');

    // Grant the member a new group role and check the storage.
    $group_role = $this->groupRoleStorage->create([
      'id' => 'default-editor',
      'label' => 'Default editor',
      'weight' => 0,
      'group_type' => 'default',
    ]);
    $group_role->set('site_wide_role', ['siterole'])->save();

    $this->assertEqualsCanonicalizing(['siterole'], $group_role->get('site_wide_role'), 'Role has site wide role configured');
    $membership = $this->group->getMember($this->account)->getGroupContent();
    $membership->group_roles[] = 'default-editor';
    $membership->save();

    // Reload account.
    $this->account = $this->entityTypeManager->getStorage('user')->load($this->account->id());
    $roles = $this->account->getRoles();
    $this->assertEqualsCanonicalizing(['authenticated', 'publisher'], $this->account->getRoles(), 'Role not inherited properly.');
    $account = $this->account;
  }

  /**
   * Asserts that the test user's group roles match a provided list of IDs.
   *
   * @param string[] $expected
   *   The group role IDs we expect the user to have.
   * @param bool $include_implied
   *   Whether to include internal group roles.
   * @param string $message
   *   The message to display for the assertion.
   */
  protected function compareMemberRoles($expected, $include_implied, $message) {
    $group_roles = $this->groupRoleStorage->loadByUserAndGroup($this->account, $this->group, $include_implied);
    $this->assertEqualsCanonicalizing($expected, array_keys($group_roles), $message);
  }

}
