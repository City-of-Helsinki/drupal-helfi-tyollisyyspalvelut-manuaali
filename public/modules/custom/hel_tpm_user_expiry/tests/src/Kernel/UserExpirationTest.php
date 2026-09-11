<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_user_expiry\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\hel_tpm_mail_tools\Utility\PreventMailUtility;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\hel_tpm_user_expiry\SettingsUtility;
use Drupal\user\UserInterface;

/**
 * Test description.
 *
 * @group hel_tpm_user_expiry
 */
final class UserExpirationTest extends GroupKernelTestBase {

  use UserCreationTrait;

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hel_tpm_user_expiry',
    'hel_tpm_user_expiry_messages_test',
    'hel_tpm_mail_tools',
    'message',
    'message_notify',
    'message_notify_test',
    'user',
    'field',
    'filter',
    'system',
    'group',
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
   * Group entity.
   *
   * @var \Drupal\group\Entity\Group
   */
  protected $group;

  /**
   * Group role storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|mixed|object
   */
  protected $groupRoleStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
    $this->installConfig(['field', 'system', 'user']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig([
      'hel_tpm_user_expiry_messages_test',
    ]);
    $this->cron = \Drupal::service('cron');
    $this->connection = Database::getConnection();
    $this->queue = $this->container->get('queue')
      ->get('hel_tpm_user_expiry_user_expiration_notification');

    $this->groupRoleStorage = $this->entityTypeManager->getStorage('group_role');
    $this->group = $this->createGroup(['type' => $this->createGroupType(['id' => 'default'])->id()]);
    $this->deleteCreatedUsers();
  }

  /**
   * Delete users created in GroupKernelTestBase.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function deleteCreatedUsers(): void {
    $user_storage = $this->entityTypeManager->getStorage('user');
    $users = $user_storage->loadMultiple([1, 2]);
    foreach ($users as $user) {
      $user->delete();
    }
  }

  /**
   * Test hel_tpm_user_expiry_cron.
   */
  public function testUserExpirationCron(): void {
    $this->cron->run();
    $last_run = \Drupal::state()->get('hel_tpm_user_expiry.last_run');
    // Confirm cron is not interrupted.
    $this->assertEquals(\Drupal::time()->getRequestTime(), $last_run);

    $this->cron->run();
    // Confirm cron is not ran again within 12 hours.
    $this->assertEquals($last_run, \Drupal::state()->get('hel_tpm_user_expiry.last_run'));

    // Confirm cron runs after 12 hours since last run.
    \Drupal::state()->set('hel_tpm_user_expiry.last_run', strtotime('12 hours', 0));
    $this->cron->run();
    $this->assertEquals(\Drupal::time()->getRequestTime(), \Drupal::state()->get('hel_tpm_user_expiry.last_run'));
  }

  /**
   * Test user expiration cron queueing.
   */
  public function testUserExpirationQueueingCron() {
    $last_access = strtotime('-166 days');
    $userId1 = $this->createUser([], NULL, FALSE, [
      'uid' => 1,
    ]);
    $userId2 = $this->createUser([], NULL, FALSE, [
      'uid' => 2,
    ]);
    $this->cron->run();
    $this->assertEquals(0, $this->queue->numberOfItems());

    $this->resetCronLastRun();

    $this->connection->update('users_field_data')
      ->condition('uid', $userId1->id())
      ->fields([
        'access' => $last_access,
        'created' => $last_access,
      ])
      ->execute();
    $this->connection->update('users_field_data')
      ->condition('uid', $userId2->id())
      ->fields([
        'access' => $last_access,
        'created' => $last_access,
      ])
      ->execute();

    // Run only hel_tpm_user_expiry_cron() to prevent queue from running.
    hel_tpm_user_expiry_cron();
    // User with id 2 is included and user with id 1 is excluded.
    $this->assertEquals(1, $this->queue->numberOfItems());
  }

  /**
   * Test user expiration notifications.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testUserExpirationNotifications() {
    $user = $this->createLastAccessUser(2);

    $this->cron->run();
    // Ensure the first notification is sent.
    $this->assertNotEmpty($this->drupalGetMails([
      'id' => 'message_notify_1st_user_account_expiry_reminder',
    ]));

    $this->cronRunHelper('-1 weeks', [$user]);
    // Ensure the second notification is not sent before time limit.
    $this->assertEmpty($this->drupalGetMails([
      'id' => 'message_notify_2nd_user_account_expiry_reminder',
    ]));

    $this->cronRunHelper('-2 weeks', [$user]);
    // Ensure the second notification is sent after time limit.
    $this->assertNotEmpty($this->drupalGetMails([
      'id' => 'message_notify_2nd_user_account_expiry_reminder',
    ]));

    $this->cronRunHelper('-1 days', [$user]);
    // Ensure the deactivation message is not sent before time limit.
    $this->assertEmpty($this->drupalGetMails([
      'id' => 'message_notify_hel_tpm_user_expiry_blocked',
    ]));

    $this->cronRunHelper('-2 days', [$user]);
    // Ensure the deactivation message is sent after time limit.
    $this->assertNotEmpty($this->drupalGetMails([
      'id' => 'message_notify_hel_tpm_user_expiry_blocked',
    ]));

    $this->cronRunHelper('-1 days', [$user]);
    $this->cronRunHelper('-2 days', [$user]);
    $this->cronRunHelper('-2 weeks', [$user]);
    $this->cronRunHelper('-30 days', [$user]);
    // Ensure no further mails are sent.
    $this->assertCount(3, $this->drupalGetMails());
  }

  /**
   * Test disabled user expiration.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testDisabledUserExpiration() {
    $user = $this->createLastAccessUser(2, 1, '-220 days');

    SettingsUtility::disableUserExpiration();

    // Ensure running cron and changing user timestamp has no effect as user
    // expiration is disabled from settings.
    $this->cron->run();
    $this->cronRunHelper('-2 weeks', [$user]);
    $this->cronRunHelper('-2 days', [$user]);
    $this->cronRunHelper('-1 year', [$user]);
    $user = $this->reloadEntity($user);
    $this->assertEquals('1', $user->get('status')->value);

    // Ensure no mail is sent as the user expiration is disabled.
    $this->assertCount(0, $this->drupalGetMails());
  }

  /**
   * Test disabling and enabling user expiration.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testDisablingAndEnabling() {
    $user = $this->createLastAccessUser(2, 1, '-220 days');

    SettingsUtility::disableUserExpiration();

    // Run cron and ensure no mail is sent as user expiration is disabled from
    // settings.
    $this->cron->run();
    $this->assertCount(0, $this->drupalGetMails());

    SettingsUtility::enableUserExpiration();

    // Ensure expiration works after re-enabling it.
    $this->cron->run();
    $this->assertCount(1, $this->drupalGetMails());
    $this->cronRunHelper('-1 days', [$user]);
    $this->cronRunHelper('-2 days', [$user]);
    $this->assertCount(1, $this->drupalGetMails());
    $this->cronRunHelper('-2 weeks', [$user]);
    $this->assertCount(2, $this->drupalGetMails());
  }

  /**
   * Test blocking user expiration mail by message template option.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBlockingMailByTemplate() {
    $this->createLastAccessUser(2, 1, '-220 days');

    // Only run specific cron function for keeping the item in queue.
    _hel_tpm_user_expiry_notification_cron();
    $this->assertEquals(1, $this->queue->numberOfItems());
    $this->assertCount(0, $this->drupalGetMails());

    // Ensure the cron run consumes the queued task but mail is not sent.
    PreventMailUtility::blockMessage(PreventMailUtility::USER_EXPIRATION);
    $this->cron->run();
    $this->assertEquals(0, $this->queue->numberOfItems());
    $this->assertCount(0, $this->drupalGetMails());

    // Ensure re-running cron does not send mail.
    $this->resetCronLastRun();
    $this->cron->run();
    $this->assertEquals(0, $this->queue->numberOfItems());
    $this->assertCount(0, $this->drupalGetMails());

    // Disabling blocking enables sending the mail.
    PreventMailUtility::blockMessage(PreventMailUtility::USER_EXPIRATION, FALSE);
    $this->resetCronLastRun();
    $this->cron->run();
    $this->assertEquals(0, $this->queue->numberOfItems());
    $this->assertCount(1, $this->drupalGetMails());
  }

  /**
   * Test user expiration deactivation.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testUserExpirationDeactivation() {
    $user = $this->createLastAccessUser(2);

    $this->cron->run();
    $user = $this->reloadEntity($user);
    // Ensure user is active after the first cron run.
    $this->assertEquals('1', $user->get('status')->value);

    $this->cronRunHelper('-2 weeks', [$user]);
    $user = $this->reloadEntity($user);
    // Ensure user is still active after two more weeks.
    $this->assertEquals('1', $user->get('status')->value);

    $this->cronRunHelper('-2 days', [$user]);
    $user = $this->reloadEntity($user);
    // Ensure user is blocked after two more days.
    $this->assertEquals('0', $user->get('status')->value);
  }

  /**
   * Test user expiration anonymization.
   *
   * @return void
   *   Void.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testUserExpirationAnonymization(): void {
    $users = [
      'blocked_user_1' => $this->createLastAccessUser(1, 0, '-220 days', '-220 days'),
      'active_recently_changed' => $this->createLastAccessUser(2, 0, '-15 days', '-15 days'),
      'blocked_recently_changed' => $this->createLastAccessUser(3, 0, '-15 days', '-15 days'),
      'active_not_recently_changed' => $this->createLastAccessUser(4, 1, '-20 days', '-40 days'),
      'blocked_not_recently_changed' => $this->createLastAccessUser(5, 0, '-20 days', '-40 days'),
    ];

    $this->group->addMember($users['blocked_not_recently_changed']);

    $this->cron->run();
    $this->cronRunHelper('-2 weeks', $users);
    $this->cronRunHelper('-2 days', $users);

    $oldValues = [
      'blocked_user_1' => $this->getFieldsForAnonymizationTest($users['blocked_user_1']),
      'active_recently_changed' => $this->getFieldsForAnonymizationTest($users['active_recently_changed']),
      'blocked_recently_changed' => $this->getFieldsForAnonymizationTest($users['blocked_recently_changed']),
      'active_not_recently_changed' => $this->getFieldsForAnonymizationTest($users['active_not_recently_changed']),
      'blocked_not_recently_changed' => $this->getFieldsForAnonymizationTest($users['blocked_not_recently_changed']),
    ];

    $this->cronRunHelper('-30 days', $users);
    $users['blocked_user_1'] = $this->reloadEntity($users['blocked_user_1']);
    $users['active_recently_changed'] = $this->reloadEntity($users['active_recently_changed']);
    $users['blocked_recently_changed'] = $this->reloadEntity($users['blocked_recently_changed']);
    $users['active_not_recently_changed'] = $this->reloadEntity($users['active_not_recently_changed']);
    $users['blocked_not_recently_changed'] = $this->reloadEntity($users['blocked_not_recently_changed']);

    // Ensure values are not anonymized for blocked user with ID 1.
    foreach ($oldValues['blocked_user_1'] as $key => $oldValue) {
      $this->assertEquals($oldValue, $users['blocked_user_1']->get($key)->value);
    }

    // Ensure values are not anonymized for active and recently changed user.
    foreach ($oldValues['active_recently_changed'] as $key => $oldValue) {
      $this->assertEquals($oldValue, $users['active_recently_changed']->get($key)->value);
    }

    // Ensure values are not anonymized for blocked and recently changed user.
    foreach ($oldValues['blocked_recently_changed'] as $key => $oldValue) {
      $this->assertEquals($oldValue, $users['blocked_recently_changed']->get($key)->value);
    }

    // Ensure values are not anonymized for active but long-unchanged user.
    foreach ($oldValues['active_not_recently_changed'] as $key => $oldValue) {
      $this->assertEquals($oldValue, $users['active_not_recently_changed']->get($key)->value);
    }

    // Ensure values are anonymized for blocked and long-unchanged user.
    foreach ($oldValues['blocked_not_recently_changed'] as $key => $oldValue) {
      $this->assertNotEquals($oldValue, $users['blocked_not_recently_changed']->get($key)->value);
    }
    $this->assertEmpty($this->group->getMembers());
  }

  /**
   * Test that messages are not sent to already blocked users.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testUserExpirationBlockedUser() {
    $blockedUser = $this->createLastAccessUser(2, 0, '-220 days');
    $blockedUserOriginalValues = $this->getFieldsForAnonymizationTest($blockedUser);
    $this->assertEquals('0', $blockedUser->get('status')->value);

    $this->cron->run();
    // Ensure the first notification is not sent to blocked user.
    $this->assertEmpty($this->drupalGetMails([
      'id' => 'message_notify_1st_user_account_expiry_reminder',
    ]));

    $this->cronRunHelper('-2 weeks', [$blockedUser]);
    // Ensure the second notification is not sent to blocked user.
    $this->assertEmpty($this->drupalGetMails([
      'id' => 'message_notify_2nd_user_account_expiry_reminder',
    ]));

    $this->cronRunHelper('-2 days', [$blockedUser]);
    // Ensure the deactivation message is not sent for blocked user.
    $this->assertEmpty($this->drupalGetMails([
      'id' => 'message_notify_hel_tpm_user_expiry_blocked',
    ]));
    // Ensure the blocked user is still blocked.
    $this->assertEquals('0', $blockedUser->get('status')->value);

    $this->cronRunHelper('-32 days', [$blockedUser]);
    $blockedUser = $this->reloadEntity($blockedUser);
    // Ensure blocked and long-unchanged user is anonymized.
    foreach ($blockedUserOriginalValues as $key => $oldValue) {
      $this->assertNotEquals($oldValue, $blockedUser->get($key)->value);
    }
    // Ensure the blocked user is still blocked.
    $this->assertEquals('0', $blockedUser->get('status')->value);
  }

  /**
   * Tests the anonymization of a blocked user.
   *
   * This method verifies that after running the cron,
   * a blocked user's specified fields are anonymized
   * and no longer match the original values.
   *
   * @return void
   *   No return value.
   */
  public function testBlockedUserAnonymization() {
    $date = new DrupalDateTime('now -1 months');
    $blockedUser = $this->createLastAccessUser(2, 1, '-250 days');
    $originalValues = $this->getFieldsForAnonymizationTest($blockedUser);
    $blockedUser->set('status', 0);
    $blockedUser->setChangedTime($date->getTimestamp());
    $blockedUser->save();

    $date = new DrupalDateTime('now -2 weeks');
    $blockedUser1 = $this->createLastAccessUser(3, 1, '-220 days');
    $originalValues1 = $this->getFieldsForAnonymizationTest($blockedUser1);

    $blockedUser1->set('status', 0);
    $blockedUser1->setChangedTime($date->getTimestamp());
    $blockedUser1->save();

    $this->cron->run();

    $blockedUser = $this->reloadEntity($blockedUser);
    $blockedUser1 = $this->reloadEntity($blockedUser1);

    foreach ($originalValues as $key => $oldValue) {
      $this->assertNotEquals($oldValue, $blockedUser->get($key)->value);
    }

    foreach ($originalValues1 as $key => $oldValue) {
      $this->assertEquals($oldValue, $blockedUser1->get($key)->value);
    }
  }

  /**
   * Test re-activated accounts stay active for configured period of time.
   *
   * @return void
   *   Void.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testReActivatedUserStaysActive() {
    $user = $this->createLastAccessUser(2, 0, '-220 days');
    $this->assertEquals('0', $user->get('status')->value);

    $user->set('status', 1);
    $user->save();
    $user = $this->reloadEntity($user);

    $this->assertEquals(\Drupal::time()->getRequestTime(), $user->get('access')->value, 'Access time not updated');

    // Confirm re-activated user isn't queued.
    hel_tpm_user_expiry_cron();
    $this->assertEquals(0, $this->queue->numberOfItems());
    $this->resetCronLastRun();

    // Set last access to.
    $user->set('access', strtotime('-166 days'));
    $user->save();

    $this->cron->run();
    $user = $this->reloadEntity($user);
    // Ensure user is active after the first cron run.
    $this->assertEquals('1', $user->get('status')->value);

    $this->cronRunHelper('-2 weeks', [$user]);
    $user = $this->reloadEntity($user);
    // Ensure user is still active after two more weeks.
    $this->assertEquals('1', $user->get('status')->value);

    $this->cronRunHelper('-2 days', [$user]);
    $user = $this->reloadEntity($user);
    // Ensure user is blocked after two more days.
    $this->assertEquals('0', $user->get('status')->value);
  }

  /**
   * Creates a user with a given inactivity period.
   *
   * @param int $uid
   *   The user id.
   * @param int $status
   *   The user status.
   * @param string $lastAccess
   *   User last access time in strtotime format.
   * @param string $lastChanged
   *   User last changed time in strtotime format.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createLastAccessUser(int $uid = 1, int $status = 1, string $lastAccess = '-166 days', string $lastChanged = '-166 days'): UserInterface {
    $access = strtotime($lastAccess);
    $changed = strtotime($lastChanged);
    $user = $this->createUser([], NULL, FALSE, [
      'uid' => $uid,
      'mail' => 'test-' . $uid . 'tpm.test',
      'field_name' => 'Test name ' . $uid,
      'field_job_title' => 'Test job title ' . $uid,
      'field_employer' => 'Test employer ' . $uid,
      'created' => $access,
      'access' => $access,
      'changed' => $changed,
      'status' => $status,
    ]);
    $this->connection->update('users_field_data')
      ->condition('uid', $user->id())
      ->fields([
        'access' => $access,
        'created' => $access,
        'changed' => $changed,
      ])
      ->execute();
    return $user;
  }

  /**
   * Helper function to get user fields for anonymization test.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return array
   *   Use field values.
   */
  protected function getFieldsForAnonymizationTest(UserInterface $user): array {
    // The name field is not tested as it's generated using another module.
    return [
      'mail' => $user->get('mail')->value,
      'pass' => $user->get('pass')->value,
      'field_name' => $user->get('field_name')->value,
      'field_job_title' => $user->get('field_job_title')->value,
      'field_employer' => $user->get('field_employer')->value,
    ];
  }

  /**
   * Helper function to run cron and related actions.
   *
   * @param string $date
   *   Date in strtotime format.
   * @param array $users
   *   An array of users.
   *
   * @return void
   *   Void.
   */
  protected function cronRunHelper(string $date, array $users): void {
    $this->resetCronLastRun();
    foreach ($users as $user) {
      $this->updateStateTimestamp($date, $user);
    }
    $this->cron->run();
  }

  /**
   * Reset last cron run state.
   */
  protected function resetCronLastRun(): void {
    \Drupal::state()->delete('hel_tpm_user_expiry.last_run');
  }

  /**
   * Update helper for expiry-notified timestamp.
   *
   * @param string $date
   *   Date in strtotime format.
   * @param \Drupal\Core\Entity\EntityInterface $user
   *   User object.
   *
   * @return void
   *   Void
   */
  protected function updateStateTimestamp(string $date, EntityInterface $user): void {
    $state = \Drupal::state()->get('hel_tpm_user_expiry.notified.' . $user->id());
    $state['timestamp'] = strtotime($date);
    \Drupal::state()->set('hel_tpm_user_expiry.notified.' . $user->id(), $state);
  }

}
