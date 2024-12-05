<?php

declare(strict_types=1);

namespace Drupal\Tests\service_manual_workflow\Kernel;

use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\service_manual_workflow\Traits\ServiceManualWorkflowTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Provides tests for ServiceArchivalQueue worker.
 */
class ServiceArchivalQueueTest extends GroupKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'system',
    'user',
    'workflows',
    'hel_tpm_group',
    'field',
    'gnode',
    'service_manual_workflow',
    'content_moderation',
    'gcontent_moderation',
    'message_notify',
    'group',
    'ggroup',
    'ggroup_role_mapper',
    'field_permissions',
    'flexible_permissions',
    'service_manual_workflow_service_test',
  ];

  use UserCreationTrait;

  use ServiceManualWorkflowTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('group');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'node', 'system']);
    $this->installSchema('ggroup', ['group_graph']);
    $this->installConfig([
      'service_manual_workflow_service_test',
      'content_moderation',
    ]);
  }

  /**
   * Test callback.
   *
   * @covers Drupal\service_manual_workflow\Form\SetServiceOutdatedOperationForm;
   */
  public function testServiceAutomaticArchival(): void {
    $queue_name = 'service_manual_workflow_service_archival_queue';
    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'outdated',
    ]);

    _service_manual_workflow_queue_services_for_archival("now");

    $queue_factory = \Drupal::service('queue');
    $queue_manager = \Drupal::service('plugin.manager.queue_worker');

    $queue_worker = $queue_manager->createInstance($queue_name);

    $queue = $queue_factory->get($queue_name);
    $item = $queue->claimItem();
    $queue_worker->processItem($item->data);

    $node = $this->reloadEntity($node);
    $this->assertEquals('archived', $node->get('moderation_state')->value);
    $this->assertFalse($node->isPublished());
  }

}
