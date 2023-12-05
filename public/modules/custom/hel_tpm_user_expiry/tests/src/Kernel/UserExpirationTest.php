<?php declare(strict_types = 1);

namespace Drupal\Tests\hel_tpm_user_expiry\Kernel;

use Drupal;
use Drupal\Core\Database\Database;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

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
   * @var \Drupal\Core\Queue\DatabaseQueue.
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
    $this->cron = Drupal::service('cron');
    $this->connection = Database::getConnection();
    $this->queue = $this->container->get('queue')
      ->get('hel_tpm_user_expiry_user_expiration_notification');
  }

  /**
   * Test hel_tpm_user_expiry_cron.
   */
  public function testUserExpirationCron(): void {
    $this->cron->run();
    $last_run = Drupal::state()->get('hel_tpm_user_expiry.last_run');
    // Confirm cron is not interrupted.
    $this->assertEquals(Drupal::time()->getRequestTime(), $last_run);

    $this->cron->run();
    // Confirm cron is not ran again within 12 hours.
    $this->assertEquals($last_run, Drupal::state()->get('hel_tpm_user_expiry.last_run'));

    // Confirm cron runs after 12 hours since last run.
    Drupal::state()->set('hel_tpm_user_expiry.last_run',  $run_time_limit = strtotime('12 hours', 0));
    $this->cron->run();
    $this->assertEquals(Drupal::time()->getRequestTime(), Drupal::state()->get('hel_tpm_user_expiry.last_run'));
  }

  /**
   * Test user expiration cron queueing.
   */
  public function testUserExpirationQueueingCron() {
    $last_access = strtotime('-3 months -2 weeks');
    $user = $this->createUser();
    $this->cron->run();
    $this->assertEquals(0, $this->queue->numberOfItems());

    $this->resetCronLastRun();

    $this->connection->update('users_field_data')
      ->condition('uid', $user->id())
      ->fields([
        'access' => $last_access,
        'created' => $last_access
      ])
      ->execute();

    // Run only hel_tpm_user_expiry_cron() to prevent queue from running.
    hel_tpm_user_expiry_cron();
    $this->assertEquals(1, $this->queue->numberOfItems());
  }

  public function testUserExpirationNotifications() {
    $last_access = strtotime('-3 months -2 weeks');
    $user = $this->createUser();
    $this->connection->update('users_field_data')
      ->condition('uid', $user->id())
      ->fields([
        'access' => $last_access,
        'created' => $last_access
      ])
      ->execute();
    $this->cron->run();
    $this->assertNotEmpty($this->drupalGetMails([
      'id' => 'message_notify_1st_user_account_expiry_reminder'
    ]));

    $this->resetCronLastRun();
    $this->updateStateTimestamp('-1 weeks', $user);
    $this->cron->run();

    // Validate that 2nd message is not sent before 2 weeks since last notification.
    $this->assertEmpty($this->drupalGetMails([
      'id' => 'message_notify_2nd_user_account_expiry_reminder'
    ]));

    $this->resetCronLastRun();
    $this->updateStateTimestamp('-2 weeks', $user);

    $this->cron->run();
    $this->assertNotEmpty($this->drupalGetMails([
      'id' => 'message_notify_2nd_user_account_expiry_reminder'
    ]));

    $this->resetCronLastRun();
    $this->updateStateTimestamp('-1 days', $user);
    $this->cron->run();
    $user = $this->reloadEntity($user);
    $this->assertEquals('1', $user->get('status')->value);
    $mails = $this->drupalGetMails();
    $this->assertCount(2, $mails);

    $this->resetCronLastRun();
    $this->updateStateTimestamp('-2 days', $user);
    $this->cron->run();
    $user = $this->reloadEntity($user);
    $this->assertEquals(0, $user->get('status')->value);

  }

  protected function resetCronLastRun() {
    Drupal::state()->delete('hel_tpm_user_expiry.last_run');
  }

  /**
   * @param string $date
   *   Date in strtotime format.
   * @param \Drupal\user\UserInterface $user
   *   User object.
   *
   * @return void
   */
  protected function updateStateTimestamp($date, $user) {
    $state = Drupal::state()->get('hel_tpm_user_expiry.notified.' . $user->id());
    $state['timestamp'] = strtotime($date);
    Drupal::state()->set('hel_tpm_user_expiry.notified.' . $user->id(), $state);
  }
}
