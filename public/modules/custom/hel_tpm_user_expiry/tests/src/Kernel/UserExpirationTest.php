<?php

declare(strict_types = 1);

namespace Drupal\Tests\hel_tpm_user_expiry\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Test description.
 *
 * @group hel_tpm_user_expiry
 */
final class UserExpirationTest extends EntityKernelTestBase {

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
    'message',
    'message_notify',
    'message_notify_test',
    'user',
    'field',
    'filter',
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
    $this->installConfig(['field', 'system']);
    $this->installConfig([
      'hel_tpm_user_expiry_messages_test',
    ]);
    $this->cron = \Drupal::service('cron');
    $this->connection = Database::getConnection();
    $this->queue = $this->container->get('queue')
      ->get('hel_tpm_user_expiry_user_expiration_notification');
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
    \Drupal::state()->set('hel_tpm_user_expiry.last_run', $run_time_limit = strtotime('12 hours', 0));
    $this->cron->run();
    $this->assertEquals(\Drupal::time()->getRequestTime(), \Drupal::state()->get('hel_tpm_user_expiry.last_run'));
  }

  /**
   * Test user expiration cron queueing.
   */
  public function testUserExpirationQueueingCron() {
    $last_access = strtotime('-165 days');
    $user = $this->createUser();
    $this->cron->run();
    $this->assertEquals(0, $this->queue->numberOfItems());

    $this->resetCronLastRun();

    $this->connection->update('users_field_data')
      ->condition('uid', $user->id())
      ->fields([
        'access' => $last_access,
        'created' => $last_access,
      ])
      ->execute();

    // Run only hel_tpm_user_expiry_cron() to prevent queue from running.
    hel_tpm_user_expiry_cron();
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
      'inactive' => $this->createLastAccessUser(2, '-250 days'),
      'active' => $this->createLastAccessUser(3, '-165 days'),
    ];

    $this->cron->run();
    $this->cronRunHelper('-2 weeks', $users);
    $this->cronRunHelper('-2 days', $users);

    $inactiveOldValues = $this->getFieldsForAnonymizationTest($users['inactive']);
    $activeOldValues = $this->getFieldsForAnonymizationTest($users['active']);

    $this->cronRunHelper('-30 days', $users);
    $users['inactive'] = $this->reloadEntity($users['inactive']);
    $users['active'] = $this->reloadEntity($users['active']);

    // Ensure values are anonymized for user with enough inactivation time.
    foreach ($inactiveOldValues as $key => $oldValue) {
      $this->assertNotEquals($oldValue, $users['inactive']->get($key)->value);
    }

    // Ensure values are not anonymized for user without enough inactivation
    // time.
    foreach ($activeOldValues as $key => $oldValue) {
      $this->assertEquals($oldValue, $users['active']->get($key)->value);
    }
  }

  /**
   * Creates a user with given inactivity period.
   *
   * @param int $uid
   *   The user id.
   * @param string $lastAccess
   *   The strtotime format of user's last access.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createLastAccessUser(int $uid = 1, string $lastAccess = '-165 days'): UserInterface {
    $access = strtotime($lastAccess);
    $user = $this->createUser([], NULL, FALSE, [
      'uid' => $uid,
      'mail' => 'test-' . $uid . 'tpm.test',
      'field_name' => 'Test name ' . $uid,
      'field_job_title' => 'Test job title ' . $uid,
      'field_employer' => 'Test employer ' . $uid,
      'created' => $access,
      'access' => $access,
    ]);
    $this->connection->update('users_field_data')
      ->condition('uid', $user->id())
      ->fields([
        'access' => $access,
        'created' => $access,
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
  protected function resetCronLastRun() {
    \Drupal::state()->delete('hel_tpm_user_expiry.last_run');
  }

  /**
   * Update expiry notified timestamp helper.
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
