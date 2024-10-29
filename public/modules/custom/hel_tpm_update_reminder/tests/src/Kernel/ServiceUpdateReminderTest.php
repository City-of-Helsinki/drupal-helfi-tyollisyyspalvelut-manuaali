<?php

declare(strict_types = 1);

namespace Drupal\Tests\hel_tpm_update_reminder\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\hel_tpm_update_reminder\UpdateReminderUtility;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Service update reminder tests.
 *
 * @group hel_tpm_update_reminder
 */
final class ServiceUpdateReminderTest extends EntityKernelTestBase {

  use UserCreationTrait;
  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'workflows',
    'node',
    'flexible_permissions',
    'gcontent_moderation',
    'message',
    'message_notify',
    'message_notify_test',
    'service_manual_workflow',
    'group',
    'ggroup',
    'hel_tpm_update_reminder',
    'hel_tpm_update_reminder_test',
  ];

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The cron service.
   *
   * @var \Drupal\Core\Cron
   */
  protected $cron;

  /**
   * The queue container.
   *
   * @var \Drupal\Core\Queue\DatabaseQueue
   */
  protected $queue;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('message');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'node', 'system']);
    $this->installConfig([
      'content_moderation',
      'hel_tpm_update_reminder_test',
    ]);

    $this->cron = \Drupal::service('cron');
    $this->connection = Database::getConnection();
    $this->queue = $this->container->get('queue')->get('hel_tpm_update_reminder_service');
  }

  /**
   * Tests running cron.
   *
   * @return void
   *     -
   */
  public function testCronRuns(): void {
    // Ensure cron runs.
    $this->cron->run();
    $this->assertEquals(\Drupal::time()->getRequestTime(), UpdateReminderUtility::getLastRun());

    // Ensure cron runs after last run has passed the time limit.
    $this->updateLastRunTimestamp();
    $limitPassedTimestamp = UpdateReminderUtility::getLastRun();
    $this->cron->run();
    $this->assertNotEquals($limitPassedTimestamp, UpdateReminderUtility::getLastRun());

    // Ensure cron does not run before last run has passed the time limit.
    $this->updateLastRunTimestamp(UpdateReminderUtility::RUN_LIMIT_HOURS - 1);
    $limitNotPassedTimestamp = UpdateReminderUtility::getLastRun();
    $this->cron->run();
    $this->assertEquals($limitNotPassedTimestamp, UpdateReminderUtility::getLastRun());
  }

  /**
   * Tests cron queueing with services having published moderation state.
   *
   * The moderation state transitions should allow cron to add the service ids
   * to the queue.
   *
   * @return void
   *     -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testQueueWithTransitions(): void {
    $daysAgo = UpdateReminderUtility::SERVICE_LIMIT_1 + 1;
    $services = [
      $this->createServiceWithTransition('draft', 'published', $daysAgo),
      $this->createServiceWithTransition('ready_to_publish', 'published', $daysAgo),
      $this->createServiceWithTransition('published', 'published', $daysAgo),
      $this->createServiceWithTransition('published', 'ready_to_publish', $daysAgo),
    ];
    // Only run specific cron function for keeping the items in queue.
    _hel_tpm_update_reminder_service_reminders();
    $this->assertEquals(4, $this->queue->numberOfItems());
  }

  /**
   * Tests cron queueing with recently checked services.
   *
   * The services that are checked before the first time limit is passed should
   * not have their ids added to the queue.
   *
   * @return void
   *    -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testQueueWithRecentlyChecked(): void {
    $daysAgo = UpdateReminderUtility::SERVICE_LIMIT_1 - 1;
    $servicesRecent = [
      $this->createServiceWithTransition('draft', 'published', $daysAgo),
      $this->createServiceWithTransition('ready_to_publish', 'published', $daysAgo),
      $this->createServiceWithTransition('published', 'published', $daysAgo),
      $this->createServiceWithTransition('published', 'ready_to_publish', $daysAgo),
    ];
    // Only run specific cron function for keeping the items in queue.
    _hel_tpm_update_reminder_service_reminders();
    $this->assertEquals(0, $this->queue->numberOfItems());
  }

  /**
   * Tests cron queueing with services not having published moderation state.
   *
   * The moderation state transitions should not allow cron to add the service
   * ids to the queue.
   *
   * @return void
   *    -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testQueueWithTransitionsNotPublished(): void {
    $daysAgo = UpdateReminderUtility::SERVICE_LIMIT_1 + 1;
    $notPublishedServices = [
      $this->createServiceWithTransition('draft', 'ready_to_publish', $daysAgo),
      $this->createServiceWithTransition('ready_to_publish', 'ready_to_publish', $daysAgo),
    ];
    // Only run specific cron function for keeping the items in queue.
    _hel_tpm_update_reminder_service_reminders();
    $this->assertEquals(0, $this->queue->numberOfItems());
  }

  /**
   * Tests sending reminder messages and finally marking service as outdated.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRemindersAndOutdated(): void {
    // Ensure the first reminder is sent.
    $checkedDaysAgo = UpdateReminderUtility::SERVICE_LIMIT_1 + 1;
    $service = $this->createServiceWithTransition('ready_to_publish', 'published', $checkedDaysAgo, TRUE);
    $this->cron->run();
    $this->assertEquals(1, count($this->getReminderMails()));

    // Ensure the second reminder is sent.
    $checkedDaysAgo = UpdateReminderUtility::SERVICE_LIMIT_2 + 1;
    $this->setCheckedTimestamp((int) $service->id(), $checkedDaysAgo);
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));

    // Ensure the service is outdated and the related message is sent.
    $checkedDaysAgo = UpdateReminderUtility::SERVICE_LIMIT_3 + 1;
    $this->setCheckedTimestamp((int) $service->id(), $checkedDaysAgo);
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getOutdatedMails()));
    $service = $this->reloadEntity($service);
    $this->assertEquals('outdated', $service->get('moderation_state')->value);

    // Ensure no further messages are sent.
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(1, count($this->getOutdatedMails()));
  }

  /**
   * Tests user saving the service prevents further messages.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testUserCheckingService(): void {
    // First simulate that enough time has passed for the first reminder and
    // then save the service to prevent sending the first reminder.
    $checkedDaysAgo = UpdateReminderUtility::SERVICE_LIMIT_1 + 1;
    $service1 = $this->createServiceWithTransition('ready_to_publish', 'published', $checkedDaysAgo, TRUE);
    $service1->set('moderation_state', 'ready_to_publish');
    $service1->save();
    $this->cronRunHelper();
    $this->assertEquals(0, count($this->getReminderMails()));
    $this->assertEquals(0, UpdateReminderUtility::getMessagesSent((int) $service1->id()));

    // First ensure the first reminder is sent, then simulate that enough time
    // has passed for the second reminder and then save the service to prevent
    // sending the second reminder.
    $service2 = $this->createServiceWithTransition('ready_to_publish', 'published', $checkedDaysAgo, TRUE);
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));
    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent((int) $service2->id()));
    $checkedDaysAgo = UpdateReminderUtility::SERVICE_LIMIT_2 + 1;
    $this->updateService((int) $service2->id(), [], $checkedDaysAgo);
    $service2->save();
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));
    $this->assertEquals(0, UpdateReminderUtility::getMessagesSent((int) $service2->id()));
  }

  /**
   * Updates last run state.
   *
   * @param int $hours
   *   Defines how many hours ago was the last run.
   *
   * @return void
   *   -
   */
  protected function updateLastRunTimestamp(int $hours = UpdateReminderUtility::RUN_LIMIT_HOURS): void {
    $timestamp = strtotime('-' . $hours . ' hours', \Drupal::time()->getRequestTime());
    \Drupal::state()->set(UpdateReminderUtility::LAST_RUN_KEY, $timestamp);
  }

  /**
   * Helper function to always run service update reminder with cron.
   *
   * @return void
   *   -
   */
  protected function cronRunHelper(): void {
    \Drupal::state()->delete(UpdateReminderUtility::LAST_RUN_KEY);
    $this->cron->run();
  }

  /**
   * Set node content as checked with past timestamp.
   *
   * @param int $nid
   *   The node id.
   *
   * @param int $days
   *   Defines how many days ago the node was checked.
   *
   * @return void
   *   -
   */
  protected function setCheckedTimestamp(int $nid, int $days): void {
    $timestamp = strtotime('-' . $days . ' days', \Drupal::time()->getRequestTime());
    \Drupal::state()->set(UpdateReminderUtility::CHECKED_TIMESTAMP_BASE_KEY . $nid, $timestamp);
  }

  /**
   * Creates service with randomized title.
   *
   * @param array $values
   *   Array of values for service node.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Node entity interface.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createService(array $values): EntityInterface {
    $values += [
      'type' => 'service',
      'title' => $this->randomMachineName(8),
    ];
    $node = Node::create($values);
    $node->save();
    return $this->reloadEntity($node);
  }

  /**
   * Updates service moderation state and sets changed and checked timestamps.
   *
   * @param int $nid
   *   The node id.
   * @param array $values
   *    Array of values for service node.
   * @param int $days
   *   Defines how many days ago the node was changed and saved.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Node entity interface.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function updateService(int $nid, array $values, int $days): EntityInterface {
    $node = Node::load($nid);
    foreach ($values as $key => $value) {
      $node->set($key, $value);
    }
    $node->save();
    $this->connection->update('node_field_data')
      ->condition('nid', $node->id())
      ->fields(['changed' => strtotime('-' . $days . ' days', \Drupal::time()->getRequestTime())])
      ->execute();
    $this->setCheckedTimestamp((int) $node->id(), $days);
    return $this->reloadEntity($node);
  }

  /**
   * Creates and updates a service with given moderation state transition.
   *
   * @param string $fromState
   *   The initial moderation state.
   * @param string $toState
   *   The updated moderation state.
   * @param int $days
   *   Defines how many days ago the service was changed and saved.
   * @param bool $addUser
   *   Defines whether service provider user is added.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createServiceWithTransition(string $fromState, string $toState, int $days, bool $addUser = FALSE): EntityInterface {
    $user = NULL;
    if ($addUser) {
      $user = $this->createUser([], NULL, FALSE, [
        'mail' => $this->randomMachineName(8) . '@tpm.test',
        'status' => 1,
      ]);
    }
    $service = $this->createService([
      'field_service_provider_updatee' => $user,
      'moderation_state' => $fromState,
    ]);
    return $this->updateService((int) $service->id(), [
      'moderation_state' => $toState
    ], $days);
  }

  /**
   * Gets an array containing all update remainder mails.
   *
   * @return array
   *   An array containing captured email messages.
   */
  protected function getReminderMails(): array {
    return $this->getMails([
      'id' => 'message_notify_hel_tpm_update_reminder_service',
    ]);
  }

  /**
   * Gets an array containing all service outdated mails.
   *
   * @return array
   *   An array containing captured email messages.
   */
  protected function getOutdatedMails(): array {
    return $this->getMails([
      'id' => 'message_notify_hel_tpm_update_reminder_outdated',
    ]);
  }

}
