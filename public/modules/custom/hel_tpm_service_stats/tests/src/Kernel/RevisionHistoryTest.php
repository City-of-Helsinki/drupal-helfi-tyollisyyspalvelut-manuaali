<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_service_stats\Kernel;

use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\hel_tpm_service_stats\Traits\HelTpmServiceStatsWorkflowTestTrait;

/**
 * Test description.
 *
 * @group hel_tpm_service_stats
 */
final class RevisionHistoryTest extends GroupKernelTestBase {

  use HelTpmServiceStatsWorkflowTestTrait;

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
    'views',
    'field_permissions',
    'flexible_permissions',
    'service_manual_workflow_service_test',
    'hel_tpm_service_stats',
    'service_manual_workflow_service_language_test',
  ];

  private $revisionHistoryService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('group');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('service_published_row');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'node', 'system']);
    $this->installSchema('ggroup', ['group_graph']);
    $this->installConfig([
      'service_manual_workflow_service_test',
      'content_moderation',
    ]);
    $this->revisionHistoryService = \Drupal::service('hel_tpm_service_stats.revision_history');
  }

  /**
   * Test revision stats creation.
   */
  public function testRevisionStatsCreation(): void {
    $user = $this->createUser([], NULL, TRUE);
    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'draft',
    ]);

    $this->setCurrentUser($user);
    $this->setNodeModerationState($node, 'ready_to_publish');

    $published_revisions = $this->revisionHistoryService->getPublishedRevisions();
    $this->assertEmpty($published_revisions);

    $this->setNodeModerationState($node, 'published');
    $published_revisions = $this->revisionHistoryService->getPublishedRevisions();
    $this->assertCount(1, $published_revisions);
    $this->assertEquals(3, $published_revisions[0]->revision_id);
    $this->assertEquals('published', $published_revisions[0]->moderation_state);

    $previous_rev = $this->revisionHistoryService->getPreviousRevision($published_revisions[0]);
    $this->assertNotEmpty($previous_rev);
    $this->assertEquals(2, $previous_rev->revision_id);
    $this->assertEquals('ready_to_publish', $previous_rev->moderation_state);

    $this->setNodeModerationState($node, 'draft');
    $this->setNodeModerationState($node, 'published');

    $published_revisions = $this->revisionHistoryService->getPublishedRevisions();

    $this->assertCount(2, $published_revisions);
    $previous_rev = $this->revisionHistoryService->getPreviousRevision($published_revisions[1]);

    $this->assertEmpty($previous_rev);
  }

  /**
   * Test service revision row.
   *
   * @return void
   *  -
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testServiceRevisionRow() {
    $user = $this->createUser([], NULL, TRUE);
    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'draft',
    ]);
    $service_row_storage = $this->entityTypeManager->getStorage('service_published_row');

    $this->setCurrentUser($user);
    $this->setNodeModerationState($node, 'ready_to_publish');

    $published_revisions = $this->revisionHistoryService->getPublishedRevisions();
    $this->assertEmpty($published_revisions);

    $this->setNodeModerationState($node, 'published');
    $published_revisions = $this->revisionHistoryService->getPublishedRevisions();

    $this->assertCount(1, $published_revisions);
    $this->assertEquals(3, $published_revisions[0]->revision_id);
    $this->assertEquals('published', $published_revisions[0]->moderation_state);

    $previous_rev = $this->revisionHistoryService->getPreviousRevision($published_revisions[0]);
    $revision = $service_row_storage->load($previous_rev->id);
    $this->assertEquals(0, $revision->publish_interval->getValue()[0]['value']);

    $this->setNodeModerationState($node, 'ready_to_publish');
    $node->setRevisionCreationTime(strtotime('now +3 days'));
    $this->setNodeModerationState($node, 'published');

    $revision = $service_row_storage->loadByProperties(['publish_vid' => $node->getRevisionId()]);
    $revision = reset($revision);

    $this->assertEquals(3, $revision->publish_interval->getValue()[0]['value']);
  }
}
