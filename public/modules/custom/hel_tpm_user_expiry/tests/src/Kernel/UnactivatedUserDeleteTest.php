<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_user_expiry\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests deletion of users who have never activated their account.
 *
 * An unactivated user is an active account (status 1) that has never logged
 * in (access 0), i.e. the user has not used the one-time login link sent on
 * account creation. Such accounts are deleted once the account is older than
 * the one-time login link lifetime.
 */
#[Group('hel_tpm_user_expiry')]
#[RunTestsInSeparateProcesses]
final class UnactivatedUserDeleteTest extends EntityKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hel_tpm_mail_tools',
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['field', 'system']);
    $this->cron = \Drupal::service('cron');
    $this->connection = Database::getConnection();
    // Set up our custom test config.
    $config = $this->config('user.settings');
    $config->set('password_reset_timeout', '604800');
    $config->save();
  }

  /**
   * Test unactivated user deletion.
   */
  public function testUnactivatedUserDelete(): void {
    $admin_user = $this->createLastAccessUser(1, "-8 days");
    $blocked_user = $this->createLastAccessUser(5, "-8 days", NULL, 0);
    $expired_user = $this->createLastAccessUser(2, "-8 days");
    $pending_user = $this->createLastAccessUser(3, "-5 days");
    $activated_user = $this->createLastAccessUser(4, "-8 days", '-1 days');

    $this->cron->run();

    // Confirm user 1 is never deleted even if it has never logged in.
    $this->assertNotNull($this->reloadUser($admin_user));

    // Confirm blocked user who has never logged in is not deleted.
    $this->assertNotNull($this->reloadUser($blocked_user));

    // Confirm unactivated user older than the password reset timeout is
    // deleted.
    $this->assertNull($this->reloadUser($expired_user));

    // Confirm unactivated user within the password reset timeout is kept.
    $pending_user = $this->reloadUser($pending_user);
    $this->assertNotNull($pending_user);

    // Confirm activated user is not deleted.
    $this->assertNotNull($this->reloadUser($activated_user));

    $this->resetCronLastRun();

    $date = new DrupalDateTime('-8 days');
    // Set pending user created to -8 days.
    $pending_user->set('created', $date->getTimestamp());
    $pending_user->save();

    $this->cron->run();
    $pending_user = $this->reloadUser($pending_user);

    // Confirm pending user is now deleted after the timeout.
    $this->assertNull($pending_user);

    // Confirm activated user is not deleted.
    $this->assertNotNull($this->reloadUser($activated_user));
  }

  /**
   * Creates a user with given created and last access times and status.
   *
   * @param int $uid
   *   The user id.
   * @param string $created
   *   Created time in strtotime format.
   * @param string|null $access
   *   Last access time in strtotime format. NULL means the user has never
   *   logged in.
   * @param int $status
   *   User status.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createLastAccessUser(
    int $uid,
    string $created,
    ?string $access = NULL,
    int $status = 1,
  ): UserInterface {
    if (!empty($access)) {
      $access = new DrupalDateTime($access);
    }
    $created = new DrupalDateTime($created);
    $user = $this->createUser([], NULL, FALSE, [
      'uid' => $uid,
      'mail' => 'test-' . $uid . '@tpm.test',
      'created' => $created->getTimestamp(),
      'access' => !empty($access) ? $access->getTimestamp() : 0,
      'status' => $status,
    ]);
    $this->connection->update('users_field_data')
      ->condition('uid', $user->id())
      ->fields([
        'access' => !empty($access) ? $access->getTimestamp() : 0,
        'created' => $created->getTimestamp(),
      ])
      ->execute();

    return $user;
  }

  /**
   * Reset last cron run state.
   */
  protected function resetCronLastRun(): void {
    \Drupal::state()->delete('hel_tpm_user_expiry.block_unactivated_users_last_run');
  }

  /**
   * Reloads the given entity from the storage and returns it.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be reloaded.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The reloaded entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function reloadUser(EntityInterface $entity):? EntityInterface {
    $controller = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $controller->resetCache([$entity->id()]);
    return $controller->load($entity->id());
  }

}
