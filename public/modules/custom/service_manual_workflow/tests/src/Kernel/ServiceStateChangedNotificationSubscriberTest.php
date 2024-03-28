<?php

namespace Drupal\Tests\service_manual_workflow\Kernel;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\service_manual_workflow\Traits\ServiceManualWorkflowTestTrait;

/**
 * Tests if a specific module is enabled.
 *
 * @covers \Drupal\service_manual_workflow\Access\ServiceOutdatedAccess
 */
class ServiceStateChangedNotificationSubscriberTest extends GroupKernelTestBase {

  use ServiceManualWorkflowTestTrait;

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'gnode',
    'workflows',
    'hel_tpm_group',
    'node',
    'field_permissions',
    'flexible_permissions',
    'gcontent_moderation',
    'message',
    'message_notify',
    'message_notify_test',
    'service_manual_workflow',
    'service_manual_workflow_service_test',
    'service_manual_workflow_notification_test_config',
    'ggroup',
    'ggroup_role_mapper',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
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

    // Create service provider group.
    $this->spUser = $this->createUserWithRoles(['editor']);
    $this->spGroup = $this->createGroup(['type' => 'service_provider']);
    $this->spGroup->addMember($this->spUser, ['group_roles' => 'service_provider-editor']);

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
   * Test responsible updatee gets proper mail when configured.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testResponsibleUpdateeNotification() {
    $content_plugin = 'group_node:service';
    $spNode = $this->createNode([
      'type' => 'service',
      'uid' => $this->spUser->id(),
      'moderation_state' => 'draft',
    ]);
    $spNode->set('field_responsible_updatee', $this->orgUser2);
    $spNode->save();
    // Add created node to group.
    $this->spGroup->addRelationship($spNode, $content_plugin);
    $spNode->set('moderation_state', 'ready_to_publish');
    $spNode->save();

    $mails = $this->drupalGetMails();
    $this->assertEquals('message_notify_group_ready_to_publish_notificat', $mails[0]['id']);
    $this->assertEquals($this->orgUser2->getEmail(), $mails[0]['to']);
  }

  /**
   * Test service ready to publish super group admin notifications.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testServiceReadyToPublishSuperGroupAdminNotifications() {
    $content_plugin = 'group_node:service';
    $spNode = $this->createNode([
      'type' => 'service',
      'uid' => $this->spUser->id(),
      'moderation_state' => 'draft',
    ]);
    $spNode->set('field_service_provider_updatee', $this->spUser);
    $spNode->save();

    // Add created node to group.
    $this->spGroup->addRelationship($spNode, $content_plugin);
    $spNode->set('moderation_state', 'ready_to_publish');
    $spNode->save();

    // Swap current user to org user.
    $this->drupalSetCurrentUser($this->orgUser);
    // Publish service node.
    $spNode->set('moderation_state', 'published');
    $spNode->save();

    $mails = $this->drupalGetMails();
    // Validate ready to publish notification
    // has been sent to group administration.
    $this->assertEquals('message_notify_group_ready_to_publish_notificat', $mails[0]['id']);
    $this->assertEquals($this->orgUser->getEmail(), $mails[0]['to']);

    // Validate service publish notification is sent.
    $this->assertEquals('message_notify_content_has_been_published', $mails[1]['id']);
    $this->assertEquals($this->spUser->getEmail(), $mails[1]['to']);

  }

}
