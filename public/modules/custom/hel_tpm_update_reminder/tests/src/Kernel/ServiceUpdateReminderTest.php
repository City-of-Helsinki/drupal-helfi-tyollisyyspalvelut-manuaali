<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_update_reminder\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\hel_tpm_general\PreventMailUtility;
use Drupal\hel_tpm_update_reminder\UpdateReminderUtility;
use Drupal\node\Entity\Node;

/**
 * Service update reminder tests.
 *
 * @group hel_tpm_update_reminder
 */
final class ServiceUpdateReminderTest extends GroupKernelTestBase {

  use UserCreationTrait;
  use AssertMailTrait;
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'content_translation',
    'language',
    'workflows',
    'node',
    'flexible_permissions',
    'message',
    'message_notify',
    'message_notify_test',
    'service_manual_workflow',
    'group',
    'ggroup',
    'gnode',
    'gcontent_moderation',
    'hel_tpm_update_reminder',
    'hel_tpm_update_reminder_test',
    'hel_tpm_general',
    'purge',
    'dblog',
    'system',
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
   * The group entity.
   *
   * @var \Drupal\group\Entity\Group
   */
  private Group $group;

  /**
   * Represents the second group of entities or configurations.
   *
   * @var mixed
   */
  private Group $group2;

  /**
   * Translation langcode.
   *
   * @var string
   */
  private $translationLangcode = 'fi';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('message');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_content');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('dblog', ['watchdog']);
    $this->installConfig(['field', 'node', 'system']);
    $this->installConfig([
      'content_moderation',
      'hel_tpm_update_reminder_test',
    ]);

    $this->cron = \Drupal::service('cron');
    $this->connection = Database::getConnection();
    $this->queue = $this->container->get('queue')->get('hel_tpm_update_reminder_service');

    $group_type = $this->createGroupType();
    $storage = $this->entityTypeManager->getStorage('group_content_type');
    $storage->createFromPlugin($group_type, 'group_node:service', [])->save();

    $this->group = $this->createGroup(['type' => $group_type->id()]);
    $this->group2 = $this->createGroup(['type' => $group_type->id()]);

    ConfigurableLanguage::createFromLangcode($this->translationLangcode)->save();
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
   * The moderation state transitions should allow cron to add the service IDs
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
   * Tests cron queueing with recently saved services.
   *
   * The services that are saved before the first time limit is passed should
   * not have their IDs added to the queue.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testQueueWithRecentlySaved(): void {
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
   * IDs to the queue.
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
    // Test with service not saved for long time, add a translation, and ensure
    // the first reminder is sent.
    $service = $this->createServiceWithTransition('ready_to_publish', 'published', UpdateReminderUtility::LIMIT_1 + 1, TRUE);
    $translation = $service->addTranslation($this->translationLangcode);
    $translation->setTitle($this->randomString());
    $translation->save();

    $this->cron->run();
    $this->assertEquals(1, count($this->getReminderMails()));

    // Run cron again and ensure the second reminder is not sent.
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));

    // Ensure the second reminder is not sent when not enough time is passed.
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

    // Ensure no new mails are sent when not enough time is passed.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_3 - 1);
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(0, count($this->getOutdatedMails()));

    // Ensure the service is outdated and the related message is sent after
    // enough time is passed.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_3 + 1);
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getOutdatedMails()));
    $service = $this->reloadEntity($service);
    $this->assertEquals('outdated', $service->get('moderation_state')->value);

    // Ensure the translation is also outdated.
    $translation = $this->reloadEntity($translation);
    $this->assertEquals('outdated', $translation->get('moderation_state')->value);

    // Ensure no further messages are immediately sent.
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(1, count($this->getOutdatedMails()));

    // After some time, ensure no further messages are sent and the service
    // stays outdated.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_3 + 1);
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(1, count($this->getOutdatedMails()));
    $this->assertEquals('outdated', $service->get('moderation_state')->value);

    // Update the service back to published state.
    $this->updateService((int) $service->id(), [
      'moderation_state' => 'published',
    ], 1);
    $service = $this->reloadEntity($service);
    $this->assertEquals('published', $service->get('moderation_state')->value);

    // Run cron again and ensure no new messages are sent.
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(1, count($this->getOutdatedMails()));
  }

  /**
   * Tests reminders with previously outdated service.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testChangeOutdatedToPublished(): void {
    // Create outdated service and pretend outdated mails have been sent
    // previously.
    $service = $this->createServiceWithTransition('published',
      'outdated',
      3 * UpdateReminderUtility::LIMIT_1,
      TRUE);
    $this->setRemindedTimestampToValue((int) $service->id(), 3 * UpdateReminderUtility::LIMIT_1);
    UpdateReminderUtility::setMessagesSentState((int) $service->id(), 3);

    // Set the current user to service owner, who is also a member of the
    // producer group.
    $this->drupalSetCurrentUser($service->getOwner());

    // Change the service state to draft and then publish it.
    $service = $this->updateService((int) $service->id(), [
      'moderation_state' => 'draft',
    ], 2 * UpdateReminderUtility::LIMIT_1);
    $service = $this->updateService((int) $service->id(), [
      'moderation_state' => 'published',
    ], UpdateReminderUtility::LIMIT_1 + 1);

    // Ensure the first reminder is sent after enough time is passed.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_1 + 1);
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));

    // Ensure the second reminder is sent after enough time is passed.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_2 + 1);
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
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

    // Test with service that is not saved for long time, but is in ready to
    // publish state.
    $this->createServiceWithTransition('draft', 'ready_to_publish', UpdateReminderUtility::LIMIT_1 + 1, TRUE);
    $this->cronRunHelper();
    $this->assertEquals(0, count($this->getReminderMails()));

    // Test with service that is not saved for long time, but is outdated.
    $this->createServiceWithTransition('published', 'outdated', UpdateReminderUtility::LIMIT_1 + 1, TRUE);
    $this->cronRunHelper();
    $this->assertEquals(0, count($this->getReminderMails()));
  }

  /**
   * Tests owner saving the service before the first reminder.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testOwnerSavingBeforeReminder(): void {
    $service = $this->createServiceWithTransition('ready_to_publish', 'published', UpdateReminderUtility::LIMIT_1 + 1, TRUE);
    // Set service owner to current user.
    $this->drupalSetCurrentUser($service->getOwner());
    $service->set('moderation_state', 'ready_to_publish');
    $service->save();
    $service = $this->reloadEntity($service);
    $this->cronRunHelper();
    // Ensure the reminder is not sent as the user has saved the service.
    $this->assertEquals(0, count($this->getReminderMails()));
    $this->assertEquals(0, UpdateReminderUtility::getMessagesSent((int) $service->id()));
  }

  /**
   * Tests owner saving the service after the first reminder.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testOwnerSavingAfterReminder(): void {
    // Ensure first reminder is sent.
    $service = $this->createServiceWithTransition('ready_to_publish', 'published', 2 * UpdateReminderUtility::LIMIT_1 + 1, TRUE);
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));
    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // Set the current user to service owner, who is also a member of the
    // producer group.
    $this->drupalSetCurrentUser($service->getOwner());

    // Update service by altering the changed timestamp to past. Ensure saving
    // has reset the messages sent info.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_2 + 1);
    $service = $this->updateService((int) $service->id(), [
      'title' => 'Updated title',
    ], UpdateReminderUtility::LIMIT_1 + 1);
    $this->assertEquals(0, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // With the altered timestamp, service is included in a cron run. Ensure the
    // first message is sent again, as the message sent info was reset during
    // saving.
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals('message_notify_hel_tpm_update_reminder_service', $this->getReminderMails()[0]['id']);
    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // Update service normally which will also change the changed time to
    // current time. Ensure saving has reset the messages sent info.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_2 + 1);
    $service->set('title', 'Updated title again');
    $service->save();
    $service = $this->reloadEntity($service);
    $this->assertEquals(0, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // As the service changed timestamp has changed, service should not be
    // included in cron run.
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(0, UpdateReminderUtility::getMessagesSent((int) $service->id()));
  }

  /**
   * Tests another user saving the service after the first reminder.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAnotherUserSavingAfterReminder(): void {
    // Ensure first reminder is sent.
    $service = $this->createServiceWithTransition('ready_to_publish', 'published', 2 * UpdateReminderUtility::LIMIT_1 + 1, TRUE);
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));
    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // Set the current user as another admin user, who is not involved with
    // the service.
    $anotherUser = $this->createUser([], NULL, TRUE);
    $this->drupalSetCurrentUser($anotherUser);

    // Update service by altering the changed timestamp to past. Ensure saving
    // has not reset the messages sent info.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_2 + 1);
    $service = $this->updateService((int) $service->id(), [
      'title' => 'Updated title',
    ], UpdateReminderUtility::LIMIT_1 + 1);
    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // With the altered timestamp, service is included in a cron run. Ensure the
    // second message is sent, as the message sent info was not reset during
    // saving.
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals('message_notify_hel_tpm_update_reminder_service2', $this->getReminderMails()[1]['id']);
    $this->assertEquals(2, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // Update service normally which will also change the changed time to
    // current time. Ensure saving has not reset the messages sent info.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_2 + 1);
    $service->set('title', 'Updated title again');
    $service->save();
    $service = $this->reloadEntity($service);
    $this->assertEquals(2, UpdateReminderUtility::getMessagesSent((int) $service->id()));
  }

  /**
   * Tests saving the service as draft.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSavingServiceAsDraft(): void {
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
    PreventMailUtility::block();
    $this->cronRunHelper();
    $this->assertEquals(0, count($this->getReminderMails()));
    $this->assertEquals(0, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // Ensure reminder process continues when sending mail works.
    PreventMailUtility::block(FALSE);
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));
    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // At second reminder, ensure the reminder process does not continue if
    // sending mail fails.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_2 + 1);
    PreventMailUtility::block();
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));
    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // Ensure reminder process continues when sending mail works.
    PreventMailUtility::block(FALSE);
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(2, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // When setting outdated, ensure the process does not continue if sending
    // mail fails.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_3 + 1);
    PreventMailUtility::block();
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(0, count($this->getOutdatedMails()));
    $this->assertEquals(2, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // Ensure reminder process continues when sending mail works.
    PreventMailUtility::block(FALSE);
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(1, count($this->getOutdatedMails()));
    $this->assertEquals(3, UpdateReminderUtility::getMessagesSent((int) $service->id()));
  }

  /**
   * Tests sending reminders when sending reminder mails are blocked.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBlockedReminderMail(): void {
    $service = $this->createServiceWithTransition('ready_to_publish', 'published', UpdateReminderUtility::LIMIT_1 + 1, TRUE);

    // Ensure blocking update reminder mails does not send update reminders or
    // continue with the update reminder process.
    PreventMailUtility::blockUpdateReminder();
    $this->cronRunHelper();
    $this->assertEquals(0, count($this->getReminderMails()));
    $this->assertEquals(0, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // Ensure unblocking update reminder mails does send mail and continues the
    // process.
    PreventMailUtility::blockUpdateReminder(FALSE);
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));
    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_2 + 1);

    // Ensure blocking update reminder mails block also the second reminder.
    PreventMailUtility::blockUpdateReminder();
    $this->cronRunHelper();
    $this->assertEquals(1, count($this->getReminderMails()));
    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // Ensure unblocking update reminder mails does send mail and continues the
    // process.
    PreventMailUtility::blockUpdateReminder(FALSE);
    // Also ensure blocking outdated mails does not affect sending reminder.
    PreventMailUtility::blockServiceOutdated();
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(2, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_3 + 1);

    // Ensure outdated mails are not send as it's still blocked.
    $this->cronRunHelper();
    $this->assertEquals(2, count($this->getReminderMails()));
    $this->assertEquals(0, count($this->getOutdatedMails()));
    $this->assertEquals(2, UpdateReminderUtility::getMessagesSent((int) $service->id()));

    // Ensure unblocking outdated mails does send mail and continues the
    // process.
    PreventMailUtility::blockServiceOutdated(FALSE);
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

    $created = new DrupalDateTime('-121 days');
    // Create 2 published nodes.
    $publishedService1 = $this->createService(['moderation_state' => 'published'], $this->group);
    $publishedService2 = $this->createService([
      'moderation_state' => 'published',
      'created' => $created->getTimestamp(),
    ], $this->group);

    // Create 1 unpublished node.
    $unpublishedService = $this->createService(['moderation_state' => 'draft'], $this->group);

    // Fetch published node IDs.
    $publishedServiceIds = $update_reminder_service->fetchPublishedServiceIds();

    // Assert the IDs of published nodes are returned and the unpublished node
    // is not included.
    $this->assertCount(1, $publishedServiceIds);
    $this->assertNotContains($publishedService1->id(), $publishedServiceIds);
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
    $this->group->addMember($user1);

    $user2 = $this->createUser([], NULL, TRUE);
    $this->group->addMember($user2);

    $user3 = $this->createUser([], NULL, TRUE);
    $this->group->addMember($user3);

    // Validate that the users were created successfully.
    $this->assertNotNull($user1->id(), 'User 1 was created successfully.');
    $this->assertNotNull($user2->id(), 'User 2 was created successfully.');
    $this->assertNotNull($user3->id(), 'User 3 was created successfully.');

    // Create 1 draft service.
    $this->setCurrentUser($user1);
    $service = $this->createService([
      'moderation_state' => 'draft',
      'created' => strtotime('-130 days '),
      'changed' => strtotime('-130 days '),
    ], $this->group);

    $this->group->addRelationship($service, 'group_node:service');

    $this->updateService((int) $service->id(), ['moderation_state' => 'published'], 129);
    $remind_service = $update_reminder_service->getServiceIdsToRemind();
    $this->assertCount(1, $remind_service);

    $this->setCurrentUser($user2);

    $this->updateService((int) $service->id(), ['moderation_state' => 'published'], 128);
    $remind_service = $update_reminder_service->getServiceIdsToRemind();
    $this->assertCount(1, $remind_service);

    $this->setCurrentUser($user1);

    $this->updateService((int) $service->id(), ['moderation_state' => 'published'], 2);
    $remind_service = $update_reminder_service->getServiceIdsToRemind();
    $this->assertCount(0, $remind_service);

    $this->updateService((int) $service->id(), [
      'moderation_state' => 'draft',
    ], 1);
    $remind_service = $update_reminder_service->getServiceIdsToRemind();
    $this->assertCount(0, $remind_service);
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
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group interface.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Node entity interface.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createService(array $values, GroupInterface $group): EntityInterface {
    $values += [
      'type' => 'service',
      'title' => $this->randomMachineName(8),
    ];
    $node = Node::create($values);
    $node->save();
    $group->addRelationship($node, 'group_node:service');

    // Ensure revisions have proper changed date after group relationship.
    if (!empty($values['changed'])) {
      $this->ensureChangedDate($node, $values['changed']);
    }
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
      $this->group->addMember($user);
    }
    // Make sure newly created services are behind new ones.
    $changed = strtotime('-' . $days . ' days 1 hours', \Drupal::time()->getRequestTime());
    $service = $this->createService([
      'field_service_provider_updatee' => $user,
      'moderation_state' => $fromState,
      'created' => $changed,
      'changed' => $changed,
      'uid' => $addUser ? $user->id() : 0,
    ], $this->group);
    $this->group->addRelationship($service, 'group_node:service');

    return $this->updateService((int) $service->id(), [
      'moderation_state' => $toState,
    ], $days);
  }

  /**
   * Ensures the service's changed date is updated to the provided timestamp.
   *
   * @param \Drupal\node\NodeInterface $service
   *   The service node whose changed date needs to be updated.
   * @param int $timestamp
   *   The timestamp to set for the changed date.
   *
   * @return void
   *   -
   */
  protected function ensureChangedDate($service, $timestamp) {
    $tables = ['node_revision' => 'revision_timestamp', 'node_field_revision' => 'changed'];
    foreach ($tables as $table => $column) {
      \Drupal::database()->update($table)
        ->fields([$column => $timestamp])
        ->condition('nid', $service->id())
        ->execute();
    }
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
