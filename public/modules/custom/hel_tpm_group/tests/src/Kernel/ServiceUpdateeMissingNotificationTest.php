<?php

declare(strict_types = 1);
namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\service_manual_workflow\Traits\ServiceManualWorkflowTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Provides tests for missing service updatees.
 */
class ServiceUpdateeMissingNotificationTest extends GroupKernelTestBase {
  use UserCreationTrait;
  use ContentModerationTestTrait;
  use ContentTypeCreationTrait;
  use ServiceManualWorkflowTestTrait;

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

  /**
   * Missing updatee service.
   *
   * @var mixed
   */
  protected $missingUpdateeServices;

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'node',
    'system',
    'user',
    'workflows',
    'hel_tpm_group',
    'field',
    'content_moderation',
    'gcontent_moderation',
    'message',
    'message_notify',
    'message_notify_test',
    'gnode',
    'group',
    'ggroup',
    'ggroup_role_mapper',
    'field_permissions',
    'flexible_permissions',
    'service_manual_workflow',
    'service_manual_workflow_service_test',
    'service_manual_workflow_notification_test_config',
  ];

  /**
   * Setup test.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('group');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('message');
    $this->installSchema('ggroup', ['group_graph']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'node', 'system']);
    $this->installConfig([
      'service_manual_workflow_service_test',
      'service_manual_workflow_notification_test_config',
      'content_moderation',
    ]);

    $this->missingUpdateeServices = \Drupal::service('hel_tpm_group.service_missing_updatees');

  }

  /**
   * Test updatee notifications when updatee account is disabled.
   *
   * @return void
   *   Void.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testServiceProviderMissingUpdatees() {
    $this->initGroups();
    $this->createServiceNode();

    $result = $this->missingUpdateeServices->getGroupServiceMissingUpdatee((int) $this->spGroup->id(), TRUE);
    $this->assertEmpty($result, 'Result not empty');

    $this->spUser->set('status', 0);
    $this->spUser->save();

    $result = $this->missingUpdateeServices->getGroupServiceMissingUpdatee((int) $this->spGroup->id(), TRUE);
    $this->assertEquals(1, $result[0], 'Disabled service provider user still has permissions to edit.');

    $result = $this->missingUpdateeServices->getGroupServiceMissingUpdatee((int) $this->orgGroup->id(), TRUE);
    $this->assertCount(1, $result, 'Expected updatee count didn\'t match');
  }

  /**
   * Test when updatee member is removed from group.
   *
   * @return void
   *   Void
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testMemberRemovedFromGroup() {
    $this->initGroups();
    $this->createServiceNode();

    $this->spGroup->removeMember($this->spUser);
    $this->orgGroup->removeMember($this->orgUser);

    $result = $this->missingUpdateeServices->getGroupServiceMissingUpdatee((int) $this->orgGroup->id());
    $this->assertEquals("user has no update access", $result[0]['errors']['field_service_provider_updatee'], 'Updatee error did not match.');
    $this->assertEquals("user has no update access", $result[0]['errors']['field_responsible_updatee'], 'Updatee error did not match.');
  }

  /**
   * Initialize groups, roles and users.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function initGroups() {
    // Create service provider group.
    $this->spUser = $this->createUserWithRoles(['editor']);
    $this->spGroup = $this->createGroup(['type' => 'service_provider']);
    $this->spGroup->addMember($this->spUser, ['group_roles' => 'service_provider-group_admin']);

    // Create organisation group.
    $this->orgGroup = $this->createGroup(['type' => 'organisation']);
    $this->orgGroup->addRelationship($this->spGroup, 'subgroup:service_provider');
    // Create user for organisation group and add it to group.
    $this->orgUser = $this->createUserWithRoles(['specialist editor', 'editor']);
    $this->orgGroup->addMember($this->orgUser, ['group_roles' => ['organisation-administrator']]);

    $this->orgUser2 = $this->createUserWithRoles(['specialist editor', 'editor']);
    $this->orgGroup->addMember($this->orgUser, ['group_roles' => ['organisation-editor']]);

    // Add service provider to organisation group as subgroup.
    $this->orgGroup->addRelationship($this->spGroup, 'subgroup:service_provider');
  }

  /**
   * Create service node.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Entity interface.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createServiceNode() {
    $content_plugin = 'group_node:service';
    $spNode = $this->createNode([
      'type' => 'service',
      'uid' => $this->spUser->id(),
      'moderation_state' => 'draft',
    ]);
    $spNode->set('field_service_producer', $this->spGroup);
    $spNode->set('field_service_provider_updatee', $this->spUser);
    $spNode->set('field_responsible_municipality', $this->orgGroup);
    $spNode->set('field_responsible_updatee', $this->orgUser);
    $spNode->save();
    // Add created node to group.
    $this->spGroup->addRelationship($spNode, $content_plugin);
    $spNode->set('moderation_state', 'published');
    $spNode->save();

    return $spNode;
  }

}
