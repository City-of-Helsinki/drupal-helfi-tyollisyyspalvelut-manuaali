<?php

namespace Drupal\Tests\hel_tpm_update_reminder\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\group\Entity\Group;
use Drupal\hel_tpm_update_reminder\UpdateReminderUtility;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\hel_tpm_update_reminder\ServiceUpdateReminderTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Provides test coverage for the reset update reminder action.
 *
 * This test class ensures the proper functionality of reminder messages
 * and transition states of services, including handling outdated status
 * and resetting reminders.
 */
final class ResetUpdateReminderActionTest extends GroupKernelTestBase {

  use UserCreationTrait;
  use AssertMailTrait;
  use ContentTypeCreationTrait;
  use ServiceUpdateReminderTrait;

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
    'hel_tpm_mail_tools',
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
   * The action to reset the update reminder.
   *
   * @var \Drupal\update\Plugin\Action\ResetUpdateReminder
   */
  private ?EntityInterface $resetUpdateReminderAction;

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
    $this->installEntitySchema('action');
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

    $this->resetUpdateReminderAction = $this->entityTypeManager->getStorage('action')
      ->load('reset_update_reminder');
  }

  /**
   * Tests the reset update reminder action functionality.
   *
   * This method verifies the behavior of the reset update reminder action by
   * simulating various scenarios such as sending reminders at different stages,
   * marking a service as outdated, and resetting reminder counts.
   *
   * @return void
   *   This method does not return a value, but ensures that the reminder
   *   functionality works as intended through assertions.
   */
  public function testResetUpdateReminderAction(): void {
    // Test with service not saved for long time, add a translation, and ensure
    // the first reminder is sent.
    $service = $this->createServiceWithTransition('ready_to_publish', 'published', UpdateReminderUtility::LIMIT_1 + 1, TRUE);

    $this->cron->run();

    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent($service->id()));

    // Ensure the second reminder is sent after enough time is passed.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_2 + 1);
    $this->cronRunHelper();
    $this->assertEquals(2, UpdateReminderUtility::getMessagesSent($service->id()));

    // Ensure the service is outdated and the related message is sent after
    // enough time is passed.
    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_3 + 1);
    $this->cronRunHelper();
    $this->assertEquals(3, UpdateReminderUtility::getMessagesSent($service->id()));
    $service = $this->reloadEntity($service);
    $this->assertEquals('outdated', $service->get('moderation_state')->value);

    // Update the service back to published state.
    $this->updateService((int) $service->id(), [
      'moderation_state' => 'published',
    ], UpdateReminderUtility::LIMIT_1);
    $service = $this->reloadEntity($service);
    $this->assertEquals('published', $service->get('moderation_state')->value);

    $this->resetUpdateReminderAction->execute([$service]);
    $this->assertEquals(0, UpdateReminderUtility::getMessagesSent($service->id()));

    $this->setRemindedTimestampToValue((int) $service->id(), UpdateReminderUtility::LIMIT_1 + 1);
    $this->cronRunHelper();

    $this->assertEquals(1, UpdateReminderUtility::getMessagesSent($service->id()));
  }

}
