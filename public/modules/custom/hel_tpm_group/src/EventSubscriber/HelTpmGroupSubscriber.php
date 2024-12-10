<?php

namespace Drupal\hel_tpm_group\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\GroupMembershipLoader;
use Drupal\hel_tpm_group\Event\GroupMembershipChanged;
use Drupal\hel_tpm_group\Event\GroupMembershipDeleted;
use Drupal\hel_tpm_group\Event\GroupSiteWideRoleChanged;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifier;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hel_tpm_group event subscriber.
 */
class HelTpmGroupSubscriber implements EventSubscriberInterface {

  use LoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoader
   */
  protected GroupMembershipLoader $membershipLoader;

  /**
   * Message notifier service.
   *
   * @var \Drupal\message_notify\MessageNotifier
   */
  protected MessageNotifier $messageNotifier;

  /**
   * Logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Default roles available.
   *
   * @var int[]
   */
  protected static $defaultRoles = [
    'editor' => 0,
    'specialist_editor' => 0,
  ];

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Entity type interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  private EntityTypeInterface $entityType;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\group\GroupMembershipLoader $membership_loader
   *   The group membership loader.
   * @param \Drupal\message_notify\MessageNotifier $message_notifier
   *   Message notifier.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(MessengerInterface $messenger, GroupMembershipLoader $membership_loader, MessageNotifier $message_notifier, EntityTypeManagerInterface $entityTypeManager) {
    $this->messenger = $messenger;
    $this->membershipLoader = $membership_loader;
    $this->messageNotifier = $message_notifier;
    $this->logger = $this->getLogger('hel_tpm_group');
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * React when site wide role is changed.
   *
   * @param \Drupal\Component\EventDispatcher\Event $event
   *   Drupal event.
   *
   * @return void
   *   Return nothing
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onGroupSiteWideRoleChanged(Event $event) {
    $group_role = $event->groupRole;
    foreach ($this->getMembersByRole($group_role) as $user) {
      $this->updateUserRoles($user);
    }
  }

  /**
   * Get all group members by role.
   *
   * @param \Drupal\group\Entity\GroupRoleInterface $group_role
   *   Group role object.
   *
   * @return array
   *   Array of group members.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getMembersByRole(GroupRoleInterface $group_role) {
    $members = [];

    foreach ($this->getGroups($group_role->getGroupTypeId()) as $group) {
      $memberships = $this->membershipLoader->loadByGroup($group, [$group_role->getOriginalId()]);
      foreach ($memberships as $membership) {
        $members[] = $membership->getUser();
      }
    }

    return $members;
  }

  /**
   * OnGroupMembershipChange Event.
   *
   * @param \Drupal\Component\EventDispatcher\Event $event
   *   Drupal event.
   *
   * @return void
   *   Returns nothing.
   */
  public function onGroupMembershipChange(Event $event): void {
    $group_content = $event->groupContent;
    $user = $group_content->getEntity();
    // No user is found for entity.
    if (empty($user)) {
      return;
    }
    $this->updateUserRoles($user);
  }

  /**
   * Block regular user if no longer a member of any group.
   *
   * @param \Drupal\Component\EventDispatcher\Event $event
   *   The event.
   *
   * @return void
   *   Void.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\message_notify\Exception\MessageNotifyException
   */
  public function onGroupMembershipDelete(Event $event): void {
    if (!$user = $event->groupContent?->getEntity()) {
      return;
    }
    if (!$user instanceof User) {
      return;
    }

    if (empty($this->membershipLoader->loadByUser($user)) && !$this->isUserAdmin($user) && $user->isActive()) {
      // User is not a member of any group and not considered to be admin user.
      // Deactivate the user.
      $user->set('status', 0);
      $user->save();
      $this->logger->info($this->t('Deactivated user ID %user_id as the user is no longer a member of any group.', [
        '%user_id' => $user->id(),
      ]));
      // Send message informing the user.
      $message = Message::create([
        'template' => 'hel_tpm_group_account_blocked',
        'uid' => $user->id(),
      ]);
      $message->set('field_user', $user);
      $message->save();
      $this->messageNotifier->send($message);
    }
  }

  /**
   * Check whether user can be considered to be admin user or not.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user.
   *
   * @return bool
   *   TRUE if user is considered admin user, FALSE otherwise.
   */
  protected function isUserAdmin(User $user): bool {
    $roles = $user->getRoles();
    if ($user->id() === 1
      || in_array('root', $roles)
      || in_array('admin', $roles)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Update users roles.
   *
   * @param \Drupal\user\UserInterface $user
   *   User object.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function updateUserRoles(UserInterface $user) {
    $roles = $this->calculateUserRoles($user);
    // Boolean used to mitigate unnecessary saves.
    $needs_save = FALSE;

    foreach ($roles as $role => $value) {
      if ($role === $value && !$user->hasRole($role)) {
        $user->addRole($role);
        $needs_save = TRUE;
        continue;
      }
      if ($value !== $role && $user->hasRole($role)) {
        $user->removeRole($role);
        $needs_save = TRUE;
      }
    }
    if ($needs_save === TRUE) {
      $user->save();
    }
  }

  /**
   * Calculate global user roles for current user from to group memberships.
   *
   * @param \Drupal\user\UserInterface $user
   *   User object.
   *
   * @return array
   *   Array of user calculated roles.
   */
  protected function calculateUserRoles(UserInterface $user) {
    $memberships = GroupMembership::loadByUser($user);
    $roles = [];
    $group_roles = [];
    foreach ($memberships as $membership) {
      $group_roles = array_merge($group_roles, $membership->getRoles());
    }
    foreach ($group_roles as $grole) {
      $swroles = $grole->getThirdPartySetting('hel_tpm_group', 'site_wide_role');
      if (empty($swroles)) {
        continue;
      }

      // Go through roles and check if prior group role
      // has already given site wide role to user
      // so we don't accidentally remove users site wide role
      // if user to another group with lesser configured roles.
      foreach ($swroles as $key => $value) {
        if ($value != 0) {
          continue;
        }
        if (isset($roles[$key]) && $roles[$key] === $key) {
          unset($swroles[$key]);
        }
      }
      $roles = array_merge($roles, $swroles);
    }

    if (empty($roles)) {
      return self::$defaultRoles;
    }

    return $roles;
  }

  /**
   * Get groups by group type.
   *
   * @param string $type
   *   Group type.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of Groups.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getGroups($type) {
    return $this->entityTypeManager->getStorage('group')->loadByProperties(['type' => $type]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      GroupSiteWideRoleChanged::EVENT_NAME => ['onGroupSiteWideRoleChanged'],
      GroupMembershipChanged::EVENT_NAME => ['onGroupMembershipChange'],
      GroupMembershipDeleted::EVENT_NAME => ['onGroupMembershipDelete'],
    ];
  }

}
