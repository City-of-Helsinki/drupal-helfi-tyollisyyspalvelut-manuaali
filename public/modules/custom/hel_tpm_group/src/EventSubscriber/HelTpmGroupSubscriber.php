<?php

namespace Drupal\hel_tpm_group\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\hel_tpm_group\Event\GroupMembershipChanged;
use Drupal\hel_tpm_group\Event\GroupSiteWideRoleChanged;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hel_tpm_group event subscriber.
 */
class HelTpmGroupSubscriber implements EventSubscriberInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

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
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
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
    $group_membership_loader = \Drupal::service('group.membership_loader');

    foreach ($this->getGroups($group_role->getGroupTypeId()) as $group) {
      $memberships = $group_membership_loader->loadByGroup($group, [$group_role->getOriginalId()]);
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
    $membership_loader = \Drupal::service('group.membership_loader');
    $memberships = $membership_loader->loadByUser($user);
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
    return \Drupal::entityTypeManager()->getStorage('group')->loadByProperties(['type' => $type]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      GroupSiteWideRoleChanged::EVENT_NAME => ['onGroupSiteWideRoleChanged'],
      GroupMembershipChanged::EVENT_NAME => ['onGroupMembershipChange'],
    ];
  }

}
