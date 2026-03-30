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
    'hel_tpm_mail_tools',
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
    'hel_tpm_service_stats_service_test',
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
   * Service stats cron service.
   *
   * @var \hel_tpm_service_statscron|object|null
   */
  private $serviceStatsCron;

  /**
   * Queue instance used for managing and processing tasks.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  private $queue;

  /**
   * Worker responsible for processing items in the queue.
   *
   * @var \Drupal\hel_tpm_service_stats\Plugin\QueueWorker\DaysSinceLastStateChangeUpdater
   */
  private $queueWorker;

  /**
   * Queue name that updates the days since the last state change.
   *
   * @var string
   */
  private $queueName = 'hel_tpm_service_stats_days_since_last_state_change_updater';

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
      'hel_tpm_service_stats_service_test',
      'content_moderation',
    ]);
    $this->revisionHistoryService = \Drupal::service('hel_tpm_service_stats.revision_history');
    $this->serviceStatsCron = \Drupal::service('hel_tpm_service_stats.cron');
    $this->queue = \Drupal::service('queue')->get($this->queueName);
    $this->queueWorker = \Drupal::service('plugin.manager.queue_worker')->createInstance($this->queueName);
    $this->database = \Drupal::database();
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
   * Tests the service translation revision history functionality.
   *
   * This method evaluates the behavior of node revisions and publishes states
   * for both default and translated content in the context of service entities.
   * It verifies data integrity between revisions and transition states for
   * multilingual scenarios.
   *
   * @return void
   *   This method performs assertions to validate revision and moderation
   *   state changes, but does not return any value.
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
   * Tests the time elapsed since the last moderation state change for a node.
   *
   * This method creates nodes and translations with different moderation states
   * and revision creation times to ensure the time calculated since the last
   * state change is accurate.
   *
   * @return void
   *   This method does not return a value but performs assertions to validate
   *   the functionality of the time calculation since the last state change.
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

  /**
   * Tests the update of the 'days since last state change' field value.
   *
   * This method validates that the field 'field_days_since_last_state_chan' is
   * correctly updated based on changes in the node's moderation state and when
   * a new revision is created. It ensures that the cron process and queue
   * worker operate as expected, and that the 'revision_translation_affected'
   * flag is set for the current revision.
   *
   * @return void
   *   No return value.
   */
  public function testDaysSinceLastStateChangeUpdate() {
    $this->createUser([], NULL, TRUE);
    $date = strtotime('-10 days');
    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'draft',
      'created' => $date,
      'changed' => $date,
    ]);

    $date = strtotime('now -9 days');
    $node->setTitle('Change 1');
    $node->setChangedTime($date);
    $node->setRevisionCreationTime($date);
    $node->setNewRevision(TRUE);
    $node->save();

    $date = strtotime('now -8 days');
    $node->setTitle('Change 2');
    $node->setChangedTime($date);
    $node->setRevisionCreationTime($date);
    $node->setNewRevision(TRUE);
    $this->setNodeModerationState($node, 'ready_to_publish');

    $this->serviceStatsCron->cron();
    self::assertEmpty($this->serviceStatsCron->cron());

    $this->serviceStatsCron->cron(TRUE);
    $item = $this->queue->claimItem();
    self::assertCount(1, $item->data);

    $this->queueWorker->processItem($item->data);
    $node = $this->reloadEntity($node);
    self::assertEquals(8, $node->field_days_since_last_state_chan->value);

    $date = strtotime('now -7 days');
    $node->setTitle('Change 3');
    $node->setChangedTime($date);
    $node->setRevisionCreationTime($date);
    $node->setNewRevision(TRUE);
    $node->save();

    $node = $this->reloadEntity($node);
    self::assertEquals(8, $node->field_days_since_last_state_chan->value);

    $revision_translation_affected = $this->database->select('node_field_data')
      ->fields('node_field_data')
      ->condition('vid', $node->getRevisionId())
      ->condition('revision_translation_affected', 1)
      ->countQuery()
      ->execute()
      ->fetchField();
    self::assertEquals(1, $revision_translation_affected);

    $revision_translation_affected = $this->database->select('node_field_revision')
      ->fields('node_field_revision')
      ->condition('vid', $node->getRevisionId())
      ->condition('revision_translation_affected', 1)
      ->countQuery()
      ->execute()
      ->fetchField();
    self::assertEquals(1, $revision_translation_affected);
  }

}
