<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_user_expiry\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;

/**
 * Test description.
 *
 * @group hel_tpm_user_expiry
 */
final class UnactivatedUserBlockTest extends EntityKernelTestBase {

  use UserCreationTrait;

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hel_tpm_user_expiry',
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
    $this->installConfig(['field', 'system']);
    $this->cron = \Drupal::service('cron');
    $this->connection = Database::getConnection();

    // Set up our custom test config.
    $config = $this->config('user.settings');
    $config->set('password_reset_timeout', '604800');
    $config->save();
  }

  /**
   * Test unactivated user blocking.
   */
  public function testUnactivatedUserBlock(): void {
    $user = $this->createLastAccessUser(2);
    $user2 = $this->createLastAccessUser(3, "-5 days");
    $user3 = $this->createLastAccessUser(4, "-8 days", '-1 days');

    $this->cron->run();

    $user = $this->reloadEntity($user);
    $this->assertEquals('0', $user->get('status')->value);

    $user2 = $this->reloadEntity($user2);
    $this->assertEquals('1', $user2->get('status')->value);

    $user3 = $this->reloadEntity($user3);
    $this->assertEquals('1', $user3->get('status')->value);

    $this->resetCronLastRun();

    // Set user2 created to -8 days.
    $user2->set('created', strtotime('-8 days'));
    $user2->save();

    $this->cron->run();
    $user2 = $this->reloadEntity($user2);

    // Confirm user2 is blocked.
    $this->assertEquals('0', $user2->get('status')->value);

    // Confirm user3 is not blocked.
    $user3 = $this->reloadEntity($user3);
    $this->assertEquals('1', $user3->get('status')->value);
  }

  /**
   * Creates a user with given inactivity period.
   *
   * @param int $uid
   *   The user id.
   * @param string $created
   *   Created timestamp.
   * @param string|null $access
   *   Last access timestamp.
   * @param int $status
   *   User status.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createLastAccessUser(int $uid = 1, string $created = '-7 days -1 minute', ?string $access = NULL, int $status = 1): UserInterface {
    $access = !empty($access) ? strtotime($access) : 0;
    $created = strtotime($created);
    $user = $this->createUser([], NULL, FALSE, [
      'uid' => $uid,
      'mail' => 'test-' . $uid . 'tpm.test',
      'created' => $created,
      'access' => $access,
      'status' => $status,
    ]);
    $this->connection->update('users_field_data')
      ->condition('uid', $user->id())
      ->fields([
        'access' => $access,
        'created' => $created,
      ])
      ->execute();

    return $user;
  }

  /**
   * Reset last cron run state.
   */
  protected function resetCronLastRun() {
    \Drupal::state()->delete('hel_tpm_user_expiry.block_unactivated_users_last_run');
  }

}
