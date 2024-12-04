<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_service_stats\Kernel;

use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\hel_tpm_service_stats\Traits\HelTpmServiceStatsWorkflowTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests for hel_tpm_service_stats.
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
    'content_translation',
    'language',
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

  /**
   * Revision history service.
   *
   * @var mixed
   */
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
    ConfigurableLanguage::createFromLangcode('fi')->save();
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
   *   -
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testServicePublishedRow() {
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

  /**
   * Test translations.
   *
   * @return void
   *   Void.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testServiceTranslationHistory() {
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

    $translation = $node->addTranslation('fi');
    $translation->setTitle('Test Translation');
    $this->setNodeModerationState($translation, 'ready_to_publish');
    $this->setNodeModerationState($translation, 'published');
    $published_revisions = $this->revisionHistoryService->getPublishedRevisions();

    $revision = end($published_revisions);
    $this->assertEquals('fi', $revision->langcode);
    $previous_rev = $this->revisionHistoryService->getPreviousRevision($revision);

    $this->assertEquals('ready_to_publish', $previous_rev->moderation_state);
    $this->assertEquals('fi', $previous_rev->langcode);
  }

  /**
   * Test time since last state change.
   */
  public function testTimeSinceLastStateChange() {
    $this->createUser([], NULL, TRUE);
    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'draft',
      'created' => strtotime("-10 days"),
      'changed' => strtotime("-10 days"),
      'revision_timestamp' => strtotime('-10 days'),
    ]);
    $node = $this->reloadEntity($node);

    $translation = $node->addTranslation('fi');
    $translation->setTitle('Test Translation');
    $translation->setUnpublished();
    $translation->setRevisionCreationTime(strtotime('-9 days'));

    $this->setNodeModerationState($translation, 'draft');

    $this->assertEquals(10, $this->revisionHistoryService->getTimeSinceLastStateChange($node));
    $this->assertEquals(9, $this->revisionHistoryService->getTimeSinceLastStateChange($translation));

    $node->setRevisionCreationTime(strtotime('-3 days'));
    $this->setNodeModerationState($node, 'ready_to_publish');

    $this->assertEquals(3, $this->revisionHistoryService->getTimeSinceLastStateChange($node));

    $node->setPublished(TRUE);
    $node->setRevisionCreationTime(strtotime('now'));
    $this->setNodeModerationState($node, 'published');

    $this->assertEquals(0, $this->revisionHistoryService->getTimeSinceLastStateChange($node));

    $this->assertEquals('draft', $node->getTranslation('fi')->moderation_state->getValue()[0]['value']);

    $translation->setRevisionCreationTime(strtotime('-3 days'));
    $this->setNodeModerationState($translation, 'ready_to_publish');

    $this->assertEquals('published', $node->moderation_state->getValue()[0]['value']);
    $this->assertEquals('ready_to_publish', $node->getTranslation('fi')->moderation_state->getValue()[0]['value']);

    $this->assertEquals(3, $this->revisionHistoryService->getTimeSinceLastStateChange($translation));

  }

}
