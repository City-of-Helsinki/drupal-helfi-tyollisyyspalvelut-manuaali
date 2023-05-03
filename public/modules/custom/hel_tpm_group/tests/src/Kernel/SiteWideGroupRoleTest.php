<?php

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\GroupRoleSynchronizer;
use Drupal\Tests\group\Functional\GroupBrowserTestBase;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;

/**
 * Test description.
 *
 * @group hel_tpm_group
 */
class SiteWideGroupRoleTest extends GroupBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['hel_tpm_group', 'group', 'options', 'entity', 'variationcache', 'group_test_config'];

  protected $groupRoleStorage;

  protected $account;

  protected $group;
  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->groupRoleStorage = $this->entityTypeManager->getStorage('group_role');
    $this->groupRoleSynchronizer = $this->container->get('group_role.synchronizer');

    $this->drupalCreateRole([], 'siterole');
    $this->createUser();
    $this->account = $this->createUser();
    $this->group = $this->createGroup();
    // Mock required services here.
    return;
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
    $group_role->set('site_wide_role', ['publisher'])->save();

    $this->assertEqualsCanonicalizing(['publisher'], $group_role->get('site_wide_role'), 'Role has site wide role configured');
    $membership = $this->group->getMember($this->account)->getGroupContent();
    $membership->group_roles[] = 'default-editor';
    $membership->save();

    // Reload account.
    $this->account = $this->entityTypeManager->getStorage('user')->load($this->account->id());

   // $this->assertEqualsCanonicalizing(['authenticated', 'publisher'], $this->account->getRoles(), 'Account has publisher role');
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
