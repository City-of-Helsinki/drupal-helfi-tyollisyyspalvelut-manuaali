<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\hel_tpm_general\PreventMailUtility;
use Drupal\hel_tpm_group\ServicesMissingUpdaters;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifier;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'hel_tpm_group_services_missing_updaters_queue' queue worker.
 *
 * @QueueWorker(
 *   id = "hel_tpm_group_services_missing_updaters_queue",
 *   title = @Translation("Service missing updaters queue"),
 *   cron = {"time" = 60},
 * )
 */
final class ServicesMissingUpdatersQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Message template used to send notifications.
   *
   * @var string
   */
  protected static string $messageTemplate = 'services_missing_updaters';

  /**
   * State api key.
   *
   * @var string
   */
  protected static string $stateName = 'hel_tpm_group_missing_updatees.group';

  /**
   * Constructs a new ServicesMissingUpdatersQueue instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ServicesMissingUpdaters $servicesMissingUpdaters,
    private readonly MessageNotifier $messageNotifier,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('hel_tpm_group.services_missing_updaters'),
      $container->get('message_notify.sender')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $group_id = $data['gid'];
    $services = $this->servicesMissingUpdaters->getByGroup($group_id, TRUE, TRUE);
    // Return if there's nothing to notify from.
    if (empty($services)) {
      return;
    }

    // Return if group can't be notified.
    if (!$this->validateNotify($group_id)) {
      return;
    }

    $this->notifyGroupAdmins($group_id);
    \Drupal::state()->set(self::$stateName . '.' . $group_id, \Drupal::time()->getRequestTime());
  }

  /**
   * Notify group administration.
   *
   * @param int $group_id
   *   Group id.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function notifyGroupAdmins($group_id) {
    // Do not proceed if sending missing updaters mail is blocked by settings.
    if (PreventMailUtility::isServiceMissingUpdatersBlocked()) {
      return;
    }

    $entity_type_manager = $this->entityTypeManager->getStorage('group');
    $group = $entity_type_manager->load($group_id);
    if (!$users = $this->getUsersToNotify($group)) {
      return;
    }

    foreach ($users as $user) {
      $this->sendNotification($user, $group);
    }
  }

  /**
   * Send notification method.
   *
   * @param \Drupal\user\UserInterface $user
   *   User object.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group object.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function sendNotification(UserInterface $user, GroupInterface $group) {
    $message = Message::create([
      'template' => self::$messageTemplate,
      'uid' => $user->id(),
    ]);
    $message->set('field_group', $group);
    $message->save();
    $this->messageNotifier->send($message);
  }

  /**
   * Get group admins from group to notify.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group object.
   *
   * @return array|null
   *   Array of users or null.
   */
  public static function getUsersToNotify(GroupInterface $group) {
    $roles = [
      'organisation-administrator',
      'service_provider-group_admin',
    ];
    $members = [];
    $memberships = GroupMembership::loadByGroup($group, $roles);

    if (empty($memberships)) {
      return;
    }
    foreach ($memberships as $membership) {
      $user = $membership->get('entity_id')->entity;
      if ($user->isBlocked()) {
        continue;
      }
      $members[$user->id()] = $user;
    }

    return $members;
  }

  /**
   * Validate notification can be sent.
   *
   * @param int $gid
   *   Group id.
   *
   * @return bool
   *   -
   */
  private function validateNotify($gid) {
    $last_reminded = \Drupal::state()->get(self::$stateName . '.' . $gid, 0);
    $limit = \Drupal::time()->getRequestTime() - strtotime("2 weeks", 0);
    if ($last_reminded > $limit) {
      return FALSE;
    }
    return TRUE;
  }

}
