<?php

namespace Drupal\Tests\service_manual_workflow\Kernel;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\service_manual_workflow\Traits\ServiceManualWorkflowTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests if a specific module is enabled.
 *
 * @covers \Drupal\service_manual_workflow\Access\ServiceOutdatedAccess
 */
class ServiceStateChangedEventTest extends GroupKernelTestBase {

  use ServiceManualWorkflowTestTrait;

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

  /**
   * Organisation group entity.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  private $orgGroup;

  /**
   * Organisation group user.
   *
   * @var \Drupal\Core\Entity\EntityInterface|\Drupal\user\Entity\User
   */
  private $orgUser;

  /**
   * Installed modules.
   *
   * @var string[]
   */
  protected static $modules = [
    'content_moderation',
    'content_translation',
    'language',
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
    $this->installEntitySchema('message');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('ggroup', ['group_graph']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'node', 'system']);
    $this->installConfig([
      'service_manual_workflow_service_test',
      'service_manual_workflow_notification_test_config',
      'content_moderation',
    ]);

    $current_user = $this->getCurrentUser();
    $current_user->set('mail', \Drupal::config('system.site')->get('mail'));
    $current_user->save();

    // Create organisation group.
    $this->orgGroup = $this->createGroup(['type' => 'organisation']);
    // Create user for organisation group and add it to group.
    $this->orgUser = $this->createUserWithRoles(['specialist editor', 'editor']);
    $this->orgGroup->addMember($this->orgUser, ['group_roles' => ['organisation-administrator']]);

    ConfigurableLanguage::createFromLangcode('fi')->save();

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
    $node = $this->createNode([
      'type' => 'service',
      'uid' => $this->orgUser->id(),
      'moderation_state' => 'draft',
    ]);
    $node->set('field_responsible_updatee', $this->orgUser);
    $node->save();
    // Add created node to group.
    $this->orgGroup->addRelationship($node, $content_plugin);
    $node->set('moderation_state', 'ready_to_publish');
    $node->save();
    $node->set('moderation_state', 'published');
    $node->save();

    $translation = $node->addTranslation('fi');

    $translation->setTitle('Test');
    $translation->set('moderation_state', 'ready_to_publish');
    $translation->save();
    $translation->set('moderation_state', 'published');
    $translation->save();

  }

}
