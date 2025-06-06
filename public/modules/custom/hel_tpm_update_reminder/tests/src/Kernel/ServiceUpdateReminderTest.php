<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_update_reminder\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\hel_tpm_general\PreventMailUtility;
use Drupal\hel_tpm_update_reminder\UpdateReminderUtility;
use Drupal\node\Entity\Node;

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
    'hel_tpm_general',
    'purge',
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
   *   -
   */
  public function testCronRuns(): void {
    // Ensure cron runs.
    $this->cron->run();
    $this->assertEquals(\Drupal::time()->getRequestTime(), UpdateReminderUtility::getLastRunTimestamp());

    // Ensure cron runs after last run has passed the time limit.
    $this->updateLastRunTimestamp();
    $limitPassedTimestamp = UpdateReminderUtility::getLastRunTimestamp();
    $this->cron->run();
    $this->assertNotEquals($limitPassedTimestamp, UpdateReminderUtility::getLastRunTimestamp());

    // Ensure cron does not run before last run has passed the time limit.
    $this->updateLastRunTimestamp(UpdateReminderUtility::RUN_LIMIT_HOURS - 1);
    $limitNotPassedTimestamp = UpdateReminderUtility::getLastRunTimestamp();
    $this->cron->run();
    $this->assertEquals($limitNotPassedTimestamp, UpdateReminderUtility::getLastRunTimestamp());
  }

  /**
   * Tests cron queueing with services having published moderation state.
   *
   * The moderation state transitions should allow cron to add the service ids
   * to the queue.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testQueueWithTransitions(): void {
    $daysAgo = UpdateReminderUtility::LIMIT_1 + 1;
    $this->createServiceWithTransition('draft', 'published', $daysAgo, TRUE);
    $this->createServiceWithTransition('ready_to_publish', 'published', $daysAgo, TRUE);
    $this->createServiceWithTransition('published', 'published', $daysAgo, TRUE);
    $this->createServiceWithTransition('published', 'ready_to_publish', $daysAgo, TRUE);
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
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testQueueWithRecentlyChecked(): void {
    $daysAgo = UpdateReminderUtility::LIMIT_1 - 1;
    $this->createServiceWithTransition('draft', 'published', $daysAgo, TRUE);
    $this->createServiceWithTransition('ready_to_publish', 'published', $daysAgo, TRUE);
    $this->createServiceWithTransition('published', 'published', $daysAgo, TRUE);
    $this->createServiceWithTransition('published', 'ready_to_publish', $daysAgo, TRUE);
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
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testQueueWithTransitionsNotPublished(): void {
    $daysAgo = UpdateReminderUtility::LIMIT_1 + 1;
    $this->createServiceWithTransition('draft', 'ready_to_publish', $daysAgo);
    $this->createServiceWithTransition('ready_to_publish', 'ready_to_publish', $daysAgo);
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
   *
   * @group reminders
   */
  public function testRemindersAndOutdated(): void {
    // Test with service that is not checked for long time and ensure the first
    // reminder is sent.
    $service = $this->createServiceWithTransition('ready_to_publish', 'published', UpdateReminderUtility::LIMIT_1 + 1, TRUE);
    $this->cron->run();
    $this->assertEquals(1, count($this->getReminderMails()));

    // Run cron again and ensure the second reminder is not sent.
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));

    // After too few days from the first reminder, run cron again and ensure the
    // second reminder is not sent.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_2 - 1);
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));

    // Ensure the second reminder is sent after enough time is passed.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_2 + 1);
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));

    // Run cron again and ensure no new messages are sent.
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(0, count($this->getOutdatedMails()));

    // After too few days from the second reminder, run cron again and ensure no
    // new messages are sent.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_3 - 1);
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(0, count($this->getOutdatedMails()));

    // Ensure the service is outdated and the related message is sent.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_3 + 1);
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getOutdatedMails()));
    $service = $this->reloadEntity($service);
    $this->assertEquals('outdated', $service->get('moderation_state')->value);

    // Ensure no further messages are sent.
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(1, count($this->getOutdatedMails()));

    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_3 + 1);
    $this->cronRunHelper();

    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(1, count($this->getOutdatedMails()));
    $this->assertEquals('outdated', $service->get('moderation_state')->value);

    // Update service back to published state.
    $this->updateService((int) $service->id(), [
      'moderation_state' => 'published',
    ], 1);
    $service = $this->reloadEntity($service);
    $this->assertEquals('published', $service->get('moderation_state')->value);

    // Run cron again and ensure no new messages are sent.
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(1, count($this->getOutdatedMails()));

    // Ensure the first reminder is sent again as the service is published
    // and enough time has passed.
    // This test might be obsolete because it
    // relies on creating new revision to the past
    // which is not realistic use case.
    /*
    $this->updateService((int) $service->id(), [
    'moderation_state' => 'published',
    ], UpdateReminderUtility::LIMIT_1 + 3);
    $service = $this->reloadEntity($service);
    $this->cronRunHelper();
    $this->assertEquals(3, count($this->getReminderMails()));
    $this->assertEquals(1,
    UpdateReminderUtility::getMessagesSent((int) $service->id()));
    // Ensure the second reminder is sent after enough time is passed.
    $this->setRemindedTimestampToValue((int) $service->id(),
    UpdateReminderUtility::LIMIT_2 + 1);
    $this->cronRunHelper();
    $this->assertEquals(4, count($this->getReminderMails()));
     */
  }

  /**
   * Tests that first reminder is not sent with different service states.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNotSendingFirstReminder(): void {
    // Test with service that is too new to be reminded for.
    $this->createServiceWithTransition('ready_to_publish', 'published', UpdateReminderUtility::LIMIT_1 - 1, TRUE);
    $this->cronRunHelper();
    $this->assertEquals(0, count($this->getReminderMails()));

    // Test with service that is not checked for long time, but is in ready to
    // publish state.
    $this->createServiceWithTransition('draft', 'ready_to_publish', UpdateReminderUtility::LIMIT_1 + 1, TRUE);
    $this->cronRunHelper();
    $this->assertEquals(0, count($this->getReminderMails()));

    // Test with service that is not checked for long time, but is outdated.
    $this->createServiceWithTransition('published', 'outdated', UpdateReminderUtility::LIMIT_1 + 1, TRUE);
    $this->cronRunHelper();
    $this->assertEquals(0, count($this->getReminderMails()));
  }

  /**
   * Tests user checking the service before the first reminder.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testUserCheckingService(): void {
    // Ensure the reminder is not sent when user marks the service as checked.
    $serviceChecked = $this->createServiceWithTransition('ready_to_publish', 'published', UpdateReminderUtility::LIMIT_1 + 1, TRUE);
    // Set service owner to current user.
    $owner = $serviceChecked->getOwner();
    $this->drupalSetCurrentUser($owner);
    $serviceChecked->set('moderation_state', 'ready_to_publish');
    $serviceChecked->save();
    $serviceChecked = $this->reloadEntity($serviceChecked);
    $this->cronRunHelper();
    $this->assertEquals(0, count($this->getReminderMails()));
    $this->assertEquals(0, UpdateReminderUtility::getMessagesSent((int) $serviceChecked->id()));
  }

  /**
   * Tests user checking the service after the first reminder.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testUserCheckingServiceAfterReminder(): void {
    // Ensure the second reminder is not sent when user marks the service as
    // checked after first reminder.
    $serviceCheckedSecond = $this->createServiceWithTransition('ready_to_publish', 'published', UpdateReminderUtility::LIMIT_1 + 1, TRUE);
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));
    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent((int) $serviceCheckedSecond->id()));
    $this->setRemindedTimestampToValue((int) $serviceCheckedSecond->id(), UpdateReminderUtility::LIMIT_2 + 1);

    $serviceCheckedSecond->save();
    $serviceCheckedSecond = $this->reloadEntity($serviceCheckedSecond);
    $this->cronRunHelper();

    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(2, UpdateReminderUtility::getMessagesSent((int) $serviceCheckedSecond->id()));

    $owner = $serviceCheckedSecond->getOwner();
    $this->drupalSetCurrentUser($owner);
    $serviceCheckedSecond->save();
    $serviceCheckedSecond = $this->reloadEntity($serviceCheckedSecond);
    $this->cronRunHelper();

    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(0, UpdateReminderUtility::getMessagesSent((int) $serviceCheckedSecond->id()));
  }

  /**
   * Tests user saving the service as draft.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testUserSavingServiceAsDraft(): void {
    // Ensure first reminder is sent as user only saves the old service as
    // draft.
    $serviceSavingAsDraft = $this->createServiceWithTransition('ready_to_publish', 'published', UpdateReminderUtility::LIMIT_1 + 1, TRUE);
    $serviceSavingAsDraft->set('moderation_state', 'draft');
    $serviceSavingAsDraft->save();
    $serviceSavingAsDraft = $this->reloadEntity($serviceSavingAsDraft);
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));
    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent((int) $serviceSavingAsDraft->id()));
  }

  /**
   * Tests sending reminders with failing mail.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFailingMail(): void {
    // At first reminder, ensure the reminder process does not continue if
    // sending mail fails.
    $service = $this->createServiceWithTransition('ready_to_publish', 'published', UpdateReminderUtility::LIMIT_1 + 1, TRUE);
    PreventMailUtility::set();
    $this->cronRunHelper();
    $this->assertEquals(0, count($this->getReminderMails()));
    $this->assertEquals(0, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // Ensure reminder process continues when sending mail works.
    PreventMailUtility::set(FALSE);
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));
    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // At second reminder, ensure the reminder process does not continue if
    // sending mail fails.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_2 + 1);
    PreventMailUtility::set();
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));
    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // Ensure reminder process continues when sending mail works.
    PreventMailUtility::set(FALSE);
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(2, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // When setting outdated, ensure the process does not continue if sending
    // mail fails.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_3 + 1);
    PreventMailUtility::set();
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(0, count($this->getOutdatedMails()));
    $this->assertEquals(2, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // Ensure reminder process continues when sending mail works.
    PreventMailUtility::set(FALSE);
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(1, count($this->getOutdatedMails()));
    $this->assertEquals(3, UpdateReminderUtility::getMessagesSent((int) $service->id()));
  }

  /**
   * Tests fetching published service IDs.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @group update_reminder_user_service
   */
  public function testFetchPublishedServiceIds(): void {
    $update_reminder_service = \Drupal::service('hel_tpm_update_reminder.update_reminder_user');

    // Create 2 published nodes.
    $publishedService1 = $this->createService(['moderation_state' => 'published']);
    $publishedService2 = $this->createService(['moderation_state' => 'published']);

    // Create 1 unpublished node.
    $unpublishedService = $this->createService(['moderation_state' => 'draft']);

    // Fetch published node IDs.
    $publishedServiceIds = $update_reminder_service->fetchPublishedServiceIds();

    // Assert the IDs of published nodes are
    // returned and the unpublished node is not included.
    $this->assertCount(2, $publishedServiceIds);
    $this->assertContains($publishedService1->id(), $publishedServiceIds);
    $this->assertContains($publishedService2->id(), $publishedServiceIds);
    $this->assertNotContains($unpublishedService->id(), $publishedServiceIds);
  }

  /**
   * Tests the retrieval of services associated with users.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when there is an issue with entity storage
   *   operations during service or user creation.
   *
   * @group update_reminder_user_service
   */
  public function testFetchServicesWithUpdaters(): void {
    $update_reminder_service = \Drupal::service('hel_tpm_update_reminder.update_reminder_user');

    $user1 = $this->createUser([], NULL, TRUE);

    $user2 = $this->createUser([], NULL, TRUE);

    $user3 = $this->createUser([], NULL, TRUE);

    // Optionally, add assertions to validate
    // that the users were created successfully.
    $this->assertNotNull($user1->id(), 'User 1 was created successfully.');
    $this->assertNotNull($user2->id(), 'User 2 was created successfully.');

    // Create 1 draft service.
    $this->setCurrentUser($user1);
    $service = $this->createService([
      'moderation_state' => 'draft',
      'field_service_provider_updatee' => $user1,
      'created' => strtotime('-130 days '),
      'changed' => strtotime('-130 days '),
    ]);

    $this->updateService((int) $service->id(), ['moderation_state' => 'published'], 129);
    $remind_service = $update_reminder_service->getServicesToRemind();
    $this->assertCount(1, $remind_service);

    $this->setCurrentUser($user2);

    $this->updateService((int) $service->id(), ['moderation_state' => 'published'], 10);
    $remind_service = $update_reminder_service->getServicesToRemind();
    $this->assertCount(1, $remind_service);

    $this->setCurrentUser($user1);

    $this->updateService((int) $service->id(), ['moderation_state' => 'published'], 2);
    $remind_service = $update_reminder_service->getServicesToRemind();
    $this->assertCount(0, $remind_service);

    $this->updateService((int) $service->id(), [
      'moderation_state' => 'draft',
      'field_service_provider_updatee' => $user3->id(),
    ], 1);
    $remind_service = $update_reminder_service->getServicesToRemind();
    $this->assertCount(0, $remind_service);

    $this->updateService((int) $service->id(), ['moderation_state' => 'published'], 0);
    $remind_service = $update_reminder_service->getServicesToRemind();
    $this->assertCount(1, $remind_service);
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
   * @param int $days
   *   Defines how many days ago the node was checked.
   *
   * @return void
   *   -
   */
  protected function setCheckedTimestampToValue(int $nid, int $days): void {
    $timestamp = strtotime('-' . $days . ' days', \Drupal::time()->getRequestTime());
    \Drupal::state()->set(UpdateReminderUtility::CHECKED_TIMESTAMP_BASE_KEY . $nid, $timestamp);
  }

  /**
   * Set node content as reminded with past timestamp.
   *
   * @param int $nid
   *   The node id.
   * @param int $days
   *   Defines how many days ago the node was reminded.
   *
   * @return void
   *   -
   */
  protected function setRemindedTimestampToValue(int $nid, int $days): void {
    $timestamp = strtotime('-' . $days . ' days', \Drupal::time()->getRequestTime());
    \Drupal::state()->set(UpdateReminderUtility::REMINDED_BASE_KEY . $nid, $timestamp);
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
    if (!empty($values['changed'])) {
      $node->setRevisionCreationTime($values['changed']);
    }
    $node->save();
    return $this->reloadEntity($node);
  }

  /**
   * Updates service moderation state and sets changed and checked timestamps.
   *
   * @param int $nid
   *   The node id.
   * @param array $values
   *   Array of values for service node.
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
    $changed = strtotime('-' . $days . ' days', \Drupal::time()->getRequestTime());

    if (!$node->isLatestRevision()) {
      $vid = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->getLatestRevisionId($nid);
      $node = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($vid);
    }
    foreach ($values as $key => $value) {
      $node->set($key, $value);
    }

    $node->setChangedTime($changed);
    $node->setRevisionCreationTime($changed);
    $node->setRevisionUserId(\Drupal::CurrentUser()->id());
    $node->save();
    $this->setCheckedTimestampToValue((int) $node->id(), $days);
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
   *   The created service.
   *
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
    if ($addUser) {
      $service->setOwner($user);
    }
    return $this->updateService((int) $service->id(), [
      'moderation_state' => $toState,
    ], $days);
  }

  /**
   * Gets an array containing all update remainder mails.
   *
   * @return array
   *   An array containing captured email messages.
   */
  protected function getReminderMails(): array {
    return array_merge(
      $this->getMails(['id' => 'message_notify_hel_tpm_update_reminder_service']),
      $this->getMails(['id' => 'message_notify_hel_tpm_update_reminder_service2'])
    );
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
