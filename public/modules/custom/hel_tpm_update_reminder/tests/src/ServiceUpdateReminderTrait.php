<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_update_reminder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\hel_tpm_update_reminder\UpdateReminderUtility;
use Drupal\node\Entity\Node;

/**
 * Provides utility methods for handling service update reminders.
 */
trait ServiceUpdateReminderTrait {

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
