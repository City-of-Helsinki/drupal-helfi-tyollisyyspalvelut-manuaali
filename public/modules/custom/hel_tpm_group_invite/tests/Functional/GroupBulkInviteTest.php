<?php

namespace Drupal\hel_tpm_group_invite\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\group\Functional\GroupBrowserTestBase;

/**
 * @coversDefaultClass \Drupal\hel_tpm_group_invite\GroupInvitation
 * @group group
 */
class GroupBulkInviteTest extends GroupBrowserTestBase {
  use StringTranslationTrait;

  /**
   * @var string[]
   */
  public static $modules = [
    'group',
    'group_test_config',
    'ginvite',
    'hel_tpm_group_invite'
  ];

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
  protected $account;

  protected $groupRole;

  /**
   * @return string[]
   */
  protected function getGlobalPermissions() {
    return [
      'view the administration theme',
      'access administration pages',
      'access group overview',
      'create default group',
      'create other group',
      'administer group',
      'bypass group access',
      'administer users'
    ];
  }

  /**
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->group = $this->createGroup(['uid' => $this->groupCreator->id()]);

    $this->account = $this->drupalCreateUser();
    $this->group->addMember($this->account);
    $this->group->save();

    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * @return void
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBulkInvite() {

    $this->drupalLogin($this->groupCreator);

    // Install and configure the Group Invitation plugin.
    $this->drupalGet('/admin/group/content/install/default/group_invitation');
    $this->submitForm([], 'Install plugin');
    $this->assertSession()->statusCodeEquals(200);

    // @todo get rid of this cache clear. But without it the group invitation
    // plugin config doesn't seem to be available.
    drupal_flush_all_caches();

    $this->drupalLogin($this->account);

    // Add permissions to invite users to members of the group.
    $role = $this->group->getGroupType()->getMemberRole();
    $role->grantPermissions(['invite users to group']);
    $role->save();

    // Add permissions to administer members to members of the group.
    $role = $this->group->getGroupType()->getMemberRole();
    $role->grantPermissions(['administer members']);
    $role->save();

    // Load invite members form.
    $this->drupalGet('/group/' . $this->group->id() . '/invite-members');
    $this->assertSession()->fieldExists('email_address');
    $this->assertSession()->fieldExists('edit-roles');
    // Validate that roles selection is required.
    $this->assertSession()->elementAttributeContains('css', 'fieldset#edit-roles--wrapper', 'required', 'required');


    // Make sure field for role selection is found.
    $role_checkbox = sprintf('//input[@value="default-custom"]');
    $this->assertSession()->elementExists('xpath', $role_checkbox);

    // Fill form and submit.
    $form = $this->getSession()->getPage();
    $form->fillField('email_address', 'test@test.test');
    $form->selectFieldOption('edit-roles', 'default-custom');
    $form->pressButton('edit-submit');
    $this->assertSession()->statusCodeEquals(200);

    // Submit confirm form.
    $form = $this->getSession()->getPage();
    $form->hasButton('edit-submit');
    $form->pressButton('edit-submit');
    $this->assertSession()->statusCodeEquals(200);
  }

}