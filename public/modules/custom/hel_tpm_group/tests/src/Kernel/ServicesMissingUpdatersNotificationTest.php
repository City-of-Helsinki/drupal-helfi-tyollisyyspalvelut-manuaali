<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\service_manual_workflow\Traits\ServiceManualWorkflowTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\hel_tpm_group\Plugin\QueueWorker\ServicesMissingUpdatersQueue;

/**
 * Provides tests for missing service updaters.
 */
class ServicesMissingUpdatersNotificationTest extends GroupKernelTestBase {
  use UserCreationTrait;
  use ContentModerationTestTrait;
  use ContentTypeCreationTrait;
  use ServiceManualWorkflowTestTrait;

  /**
   * Service provider group entity.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  private $spGroup;

  /**
   * Service Provide user entity.
   *
   * @var \Drupal\user\UserInterface
   */
  private $spUser;

  /**
   * Organization group entity.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  private $orgGroup;

  /**
   * Organization user.
   *
   * @var \Drupal\user\UserInterface
   */
  private $orgUser;

  /**
   * Organization user 2.
   *
   * @var \Drupal\user\UserInterface
   */
  private $orgUser2;

  /**
   * Organization user 3.
   *
   * @var \Drupal\user\UserInterface
   */
  private $orgUser3;

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

  /**
   * Missing updaters service.
   *
   * @var mixed
   */
  protected $servicesMissingUpdaters;

  /**
   * Service missing updaters queue.
   *
   * @var mixed|object|null
   */
  protected $queue;

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

    $this->queue = $this->container->get('queue')->get('hel_tpm_group_services_missing_updaters_queue');
    $this->servicesMissingUpdaters = \Drupal::service('hel_tpm_group.services_missing_updaters');
  }

  /**
   * Test notifications when updater account is disabled.
   *
   * @return void
   *   Void.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testDisabledUpdater() {
    $this->initGroups();
    $this->createServiceNode();

    $result = $this->servicesMissingUpdaters->getByGroup((int) $this->spGroup->id(), TRUE);
    $this->assertEmpty($result, 'Result not empty');

    $this->spUser->set('status', 0);
    $this->spUser->save();

    $result = $this->servicesMissingUpdaters->getByGroup((int) $this->spGroup->id(), TRUE);
    $this->assertEquals(1, $result[0], 'Disabled service provider user still has permissions to edit.');

    $result = $this->servicesMissingUpdaters->getByGroup((int) $this->orgGroup->id(), TRUE);
    $this->assertCount(1, $result, 'Expected updater count didn\'t match');

    $users = ServicesMissingUpdatersQueue::getUsersToNotify($this->orgGroup);
    $this->assertNotEmpty($users[$this->orgUser->id()], 'Expected user missing.');
    $this->assertTrue(empty($users[$this->orgUser3->id()]), 'Disabled user in users to sent message.');
    $this->assertCount(1, $users);
  }

  /**
   * Test updater removed from the group.
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

    $result = $this->servicesMissingUpdaters->getByGroup((int) $this->orgGroup->id());
    $this->assertEquals(
      "user has no update access",
      $result[0]['errors']['field_service_provider_updatee'],
      'Updater error did not match.'
    );
    $this->assertEquals(
      "user has no update access",
      $result[0]['errors']['field_responsible_updatee'],
      'Updater error did not match.'
    );
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
    $this->orgGroup->addMember($this->orgUser2, ['group_roles' => ['organisation-editor']]);

    $this->orgUser3 = $this->createUserWithRoles(['specialist editor', 'editor']);
    $this->orgGroup->addMember($this->orgUser3, ['group_roles' => ['organisation-administrator']]);
    // Disable orgUser3.
    $this->orgUser3->set('status', 0);
    $this->orgUser3->save();

    // Add service provider to organisation group as subgroup.
    $this->orgGroup->addRelationship($this->spGroup, 'subgroup:service_provider');

    $this->orgGroup->save();
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
