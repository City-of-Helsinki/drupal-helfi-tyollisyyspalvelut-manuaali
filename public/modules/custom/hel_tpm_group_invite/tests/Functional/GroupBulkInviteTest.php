<?php

namespace Drupal\hel_tpm_group_invite\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\group\PermissionScopeInterface;
use Drupal\Tests\group\Functional\GroupBrowserTestBase;
use Drupal\user\RoleInterface;

/**
 * @coversDefaultClass \Drupal\hel_tpm_group_invite\GroupInvitation
 * @group group
 */
class GroupBulkInviteTest extends GroupBrowserTestBase {
  use StringTranslationTrait;

  /**
   * Required modules.
   *
   * @var string[]
   */
  protected static $modules = [
    'group',
    'group_test_config',
    'ginvite',
    'hel_tpm_group_invite',
  ];

  /**
   * The group we will use to test methods on.
   *
   * @var \Drupal\group\Entity\Group
   */
  protected $group;

  /**
   * The normal user we will use.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $nonGroupMemeber;

  /**
   * Group admin user.
   *
   * @var \Drupal\user\Entity\User|false
   */
  private \Drupal\user\Entity\User|false $groupAdmin;

  /**
   * Global permissions.
   *
   * @return string[]
   *   Array of permissions.
   */
  protected function getGlobalPermissions() {
    return [
      'view the administration theme',
      'access administration pages',
      'access group overview',
      'create default group',
      'administer group',
      'administer users',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $type = $this->entityTypeManager->getStorage('group_type')->load('default');

    $this->groupAdmin = $this->createUser($this->getGlobalPermissions());
    $this->nonGroupMemeber = $this->createUser();

    $this->group = $this->createGroup([
      'type' => $type->id(),
    ]);

    // Set permissions for content moderation in the default group type.
    $this->createGroupRole([
      'group_type' => $type->id(),
      'scope' => PermissionScopeInterface::INSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
    ]);

    $adminRole = $this->createGroupRole([
      'group_type' => $type->id(),
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'admin' => TRUE,
    ]);

    $this->group->addMember($this->nonGroupMemeber);
    $this->group->addMember($this->groupAdmin, ['group_roles' => [$adminRole->id()]]);
    $this->group->save();
  }

  /**
   * Test bulk invitation.
   */
  public function testBulkInvite() {
    $this->drupalLogin($this->groupAdmin);

    // Install and configure the Group Invitation plugin.
    $path = sprintf('/admin/group/content/install/%s/group_invitation', $this->group->getGroupType()->id());
    $this->drupalGet($path);
    $this->submitForm([], 'Install plugin');
    $this->assertSession()->statusCodeEquals(200);

    // @todo get rid of this cache clear. But without it the group invitation
    // plugin config doesn't seem to be available.
    drupal_flush_all_caches();

  //  $this->drupalLogin($this->nonGroupMemeber);

    // Add permissions to invite users to members of the group.
    $role = $this->group->getGroupType()->getRoles(FALSE);
    $role = reset($role);

    // Load invite members form.
    $this->drupalGet('/group/' . $this->group->id() . '/invite-members');
    $this->assertSession()->fieldExists('email_address');
    $this->assertSession()->fieldExists('edit-roles');
    // Validate that roles selection is required.
    $this->assertSession()->elementAttributeContains('css', 'fieldset#edit-roles--wrapper', 'required', 'required');

    // Make sure field for role selection is found.
    $role_checkbox = sprintf('//input[@value="%s"]', $role->id());
    $this->assertSession()->elementExists('xpath', $role_checkbox);

    // Fill form and submit.
    $form = $this->getSession()->getPage();
    $form->fillField('email_address', 'test@test.test');
    $form->selectFieldOption('edit-roles', $role->id());
    $form->pressButton('edit-submit');

    $this->assertSession()->statusCodeEquals(200);

    // Submit confirm form.
    $form = $this->getSession()->getPage();
    $form->hasButton('edit-submit');
    $form->pressButton('edit-submit');
    $this->assertSession()->statusCodeEquals(200);
  }

}
