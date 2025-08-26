<?php

namespace Drupal\Tests\hel_tpm_group\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\group\Functional\GroupBrowserTestBase;
use Drupal\group\Entity\Group;
use Drupal\group\PermissionScopeInterface;
use Drupal\user\RoleInterface;

/**
 * Tests leaving group access.
 *
 * @coversDefaultClass \Drupal\hel_tpm_group\Plugin\Group\RelationHandler\LeaveGroupAccessControl
 * @group group
 */
class LeaveGroupTest extends GroupBrowserTestBase {
  use StringTranslationTrait;

  /**
   * Required modules.
   *
   * @var string[]
   */
  protected static $modules = [
    'group',
    'group_test_config',
    'ggroup',
    'hel_tpm_group',
    'message_notify',
    'views',
  ];

  /**
   * The group we will use to test methods on.
   *
   * @var \Drupal\group\Entity\Group
   */
  protected Group $group;

  /**
   * Global permissions.
   *
   * @return string[]
   *   Array of permissions.
   */
  protected function getGlobalPermissions(): array {
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
    $this->setUpAccount();

    $this->group = $this->createGroup(['type' => $this->createGroupType()->id()]);
  }

  /**
   * Check access to 'Remove member' action.
   *
   * @todo Add support for testing the 'leave group' permission.
   *
   * @dataProvider provideLeavingGroupScenarios
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testLeavingGroup($permissions = []) {
    $this->createGroupRole([
      'group_type' => $this->group->bundle(),
      'scope' => PermissionScopeInterface::INSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'permissions' => $permissions,
    ]);

    // Add extra users.
    $this->group->addMember($this->createUser());
    $this->group->addMember($this->createUser());
    $this->group->save();

    $this->drupalGet('/group/' . $this->group->id() . '/members');
    $this->assertSession()->statusCodeEquals(200);

    $page = $this->getSession()->getPage();
    $memberTableRows = $page->findAll('css', 'main table tbody tr');
    foreach ($memberTableRows as $row) {
      $userName = $row->find('css', 'td.views-field-name')->getText();
      $removeLink = $row->find('css', 'td.views-field-dropbutton')->findLink('Remove member');
      if ($userName === $this->groupCreator->getAccountName()) {
        $this->assertNull($removeLink);
      }
      else {
        $this->assertNotNull($removeLink);
      }
    }
  }

  /**
   * Data provider for testLeavingGroup().
   */
  public function provideLeavingGroupScenarios() {
    $scenarios['canNotLeaveGroup'] = [
      [
        'view group',
        'edit group',
        'delete group',
        'administer members',
      ],
    ];

    return $scenarios;
  }

}
