<?php

declare(strict_types=1);

namespace Drupal\Tests\service_manual_workflow\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\language\Entity\ConfigurableLanguage;
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
    'content_translation',
    'language',
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
   * Holds the language code for translations.
   *
   * @var string
   */
  protected string $translationLangcode = 'fi';

  /**
   * Queue name.
   *
   * @var string
   */
  protected string $queue = 'service_manual_workflow_service_archival_queue';

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
    ConfigurableLanguage::createFromLangcode($this->translationLangcode)->save();

  }

  /**
   * Test callback.
   */
  public function testServiceAutomaticArchival(): void {
    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'outdated',
    ]);

    $translation = $node->addTranslation($this->translationLangcode, ['title' => $this->randomString()]);
    _service_manual_workflow_queue_services_for_archival("now");

    $queue_factory = \Drupal::service('queue');
    $queue_manager = \Drupal::service('plugin.manager.queue_worker');

    $queue_worker = $queue_manager->createInstance($this->queue);

    $queue = $queue_factory->get($this->queue);
    $item = $queue->claimItem();
    $queue_worker->processItem($item->data);

    $node = $this->reloadEntity($node);
    $this->assertEquals('archived', $node->get('moderation_state')->value);
    $this->assertFalse($node->isPublished());

    $translation = $this->reloadEntity($translation);
    $this->assertEquals('archived', $translation->get('moderation_state')->value);
    $this->assertFalse($translation->isPublished());
  }

  /**
   * Tests the automatic archiving of translated service nodes.
   */
  public function testServiceAutomaticArchivingTranslation(): void {
    $date = new DrupalDateTime('now -2 weeks');
    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'published',
      'created' => $date->getTimestamp(),
      'changed' => $date->getTimestamp(),
    ]);

    $translation = $node->addTranslation($this->translationLangcode, ['title' => $this->randomString()]);
    $translation->set('moderation_state', 'draft');
    $translation->set('created', $date->getTimestamp());
    $translation->set('changed', $date->getTimestamp());
    $translation->save();
    $date = $date->modify('+1 weeks');
    $translation = $this->reloadEntity($translation);
    $translation = $translation->getTranslation($this->translationLangcode);
    $translation->set('moderation_state', 'outdated');
    $translation->set('created', $date->getTimestamp());
    $translation->set('changed', $date->getTimestamp());
    $translation->save();

    _service_manual_workflow_queue_services_for_archival("-1 week 1 day");
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get($this->queue);

    $this->assertEquals(0, $queue->numberOfItems());

    $date = $date->modify('+1 day');
    $node = $this->reloadEntity($node);
    $node->set('moderation_state', 'outdated');
    $node->set('created', $date->getTimestamp());
    $node->set('changed', $date->getTimestamp());
    $node->save();

    _service_manual_workflow_queue_services_for_archival("-5 days");

    $this->assertEquals(1, $queue->numberOfItems());
    $item = $queue->claimItem();
    $this->assertEquals(4, $item->data['vid']);

  }

}
