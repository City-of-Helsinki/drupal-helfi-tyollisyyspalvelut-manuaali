<?php

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\PermissionScopeInterface;
use Drupal\hel_tpm_group\Event\GroupMembershipChanged;
use Drupal\hel_tpm_group\Event\GroupSiteWideRoleChanged;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;

/**
 * Test description.
 *
 * @group hel_tpm_group
 */
class HelTpmGroupSubscriberTest extends GroupKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['hel_tpm_group', 'group'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('group_role');

    $this->groupRoleStorage = $this->entityTypeManager->getStorage('group_role');
    $this->group = $this->createGroup(['type' => $this->createGroupType(['id' => 'default'])->id()]);
    $this->account = $this->createUser();
    $this->group_role = $this->createGroupRole([
      'id' => 'group-editor',
      'label' => 'editor',
    ]);

    $this->createRole([], 'editor', 'Editor');
    $this->createRole([], 'publisher', 'Publisher');

    $this->group->addMember($this->account);

    // Create group role.
  }

  /**
   * Test onGroupMembershipChange method.
   */
  public function testOnGroupMembershipChange() {
    $this->group_role->setThirdPartySetting('hel_tpm_group', 'site_wide_role', [
      'editor' => 'editor',
      'publisher' => 0,
    ])->save();
    // Add group-editor role to user and validate
    // user gets editor site-wide role.
    $this->editUserGroupRoles(['group-editor']);
    $roles = $this->getUserGroupRoles();
    $this->assertEqualsCanonicalizing(['group-editor'], array_keys($roles));
    $this->assertEqualsCanonicalizing(['editor', 'authenticated'], $this->account->getRoles());

    // Remove group role and confirm editor role is removed from user.
    $this->editUserGroupRoles([]);
    $this->assertEqualsCanonicalizing(['authenticated'], $this->account->getRoles());
  }

  /**
   * Test onGroupSiteWideRoleChanged method.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testOnGroupSiteWideRoleChanged() {
    // Test setting initial role.
    $this->group_role->setThirdPartySetting('hel_tpm_group', 'site_wide_role', [
      'publisher' => 0,
      'editor' => 'editor',
    ])->save();
    $this->assertEqualsCanonicalizing(['authenticated'], $this->account->getRoles());
    $this->editUserGroupRoles(['group-editor']);
    $this->assertEqualsCanonicalizing(['editor', 'authenticated'], $this->account->getRoles());

    // Test changing the role.
    $this->group_role->setThirdPartySetting('hel_tpm_group', 'site_wide_role', [
      'publisher' => 'publisher',
      'editor' => 0,
    ])->save();
    $this->dispatchSiteWideRoleChanged($this->group_role);
    $this->reloadUser();
    $this->assertEqualsCanonicalizing(['publisher', 'authenticated'], $this->account->getRoles());

    // Test that user can have multiple site wide roles.
    $this->group_role->setThirdPartySetting('hel_tpm_group', 'site_wide_role', [
      'publisher' => 'publisher',
      'editor' => 'editor',
    ])->save();
    $this->dispatchSiteWideRoleChanged($this->group_role);
    $this->reloadUser();
    $this->assertEqualsCanonicalizing(['publisher', 'editor', 'authenticated'], $this->account->getRoles());

  }

  /**
   * Helper method to get user group roles.
   *
   * @return \Drupal\group\Entity\GroupRoleInterface[]
   *   Array of group role objects.
   */
  protected function getUserGroupRoles() {
    $member = $this->group->getMember($this->account);
    return $member->getRoles();
  }

  /**
   * Helper method to reload account information.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function reloadUser() {
    $this->account = $this->entityTypeManager->getStorage('user')->load($this->account->id());
  }

  /**
   * Helper method to change group roles.
   *
   * @param array $roles
   *   Array of user roles.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function editUserGroupRoles($roles = []) {
    $r_array = [];
    $member = $this->group->getMember($this->account);
    $group_content = $member->getGroupRelationship();
    foreach ($roles as $role) {
      $r_array['target_id'] = $role;
    }
    $group_content->group_roles->setValue($r_array);
    $group_content->save();
    $this->dispatchGroupMembershipChangedEvent($group_content);
    // Reload user data.
    $this->reloadUser();
  }

  /**
   * Helper method to dispatch GroupMembershipChangedEvent.
   *
   * @param \Drupal\group\Entity\GroupRelationshipInterface $group_content
   *   Group membership content interface.
   *
   * @return void
   *   -
   *
   * @throws \Exception
   */
  protected function dispatchGroupMembershipChangedEvent(GroupRelationshipInterface $group_content): void {
    $event = new GroupMembershipChanged($group_content);
    $this->container->get('event_dispatcher')->dispatch($event, $event::EVENT_NAME);
  }

  /**
   * Helper dispatcher for siteWideRoleChanged event.
   *
   * @param \Drupal\group\Entity\GroupRoleInterface $group_role
   *   Group role object.
   *
   * @return void
   *   -
   *
   * @throws \Exception
   */
  protected function dispatchSiteWideRoleChanged(GroupRoleInterface $group_role): void {
    $event = new GroupSiteWideRoleChanged($group_role);
    $this->container->get('event_dispatcher')->dispatch($event, $event::EVENT_NAME);
  }

  /**
   * Create group role and add mapping for site_wide_role.
   *
   * @param array $values
   *   Entity values.
   *
   * @return \Drupal\group\Entity\GroupRoleInterface
   *   Group role interface object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createGroupRole(array $values = []): GroupRoleInterface {
    // Grant the member a new group role and check the storage.
    $group_role = $this->groupRoleStorage->create([
      'id' => $values['id'],
      'label' => $values['label'],
      'weight' => 1986,
      'admin' => FALSE,
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'group_type' => 'default',
      'permissions' => [],
    ]);
    $group_role->save();
    return $group_role;
  }

}
