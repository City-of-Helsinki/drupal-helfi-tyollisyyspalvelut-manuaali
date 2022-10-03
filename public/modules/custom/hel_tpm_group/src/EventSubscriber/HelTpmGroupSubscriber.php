<?php

namespace Drupal\hel_tpm_group\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\group\GroupMembership;
use Drupal\hel_tpm_group\Event\GroupMembershipChanged;
use Drupal\hel_tpm_group\Event\GroupMembershipDeleted;
use Drupal\hel_tpm_group\Event\GroupSiteWideRoleChanged;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * hel_tpm_group event subscriber.
 */
class HelTpmGroupSubscriber implements EventSubscriberInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * @var int[]
   */
  protected static $defaultRoles = [
    'editor' => 0,
    'specialist editor' => 0
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
   * @param \Drupal\Component\EventDispatcher\Event $event
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onGroupSiteWideRoleChanged(Event $event) {
    $group_role = $event->group_role;
    foreach ($this->getMembersByRole($group_role) as $user) {
      $this->updateUserRoles($user);
    }
  }

  /**
   * @param $group_role
   *
   * @return array
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
   * @param \Drupal\Component\EventDispatcher\Event $event
   *
   * @return void
   */
  public function onGroupMembershipChange(Event $event) {
    $group_content = $event->group_content;
    $user = $group_content->getEntity();
    $this->updateUserRoles($user);
  }

  /**
   * @param \Drupal\user\UserInterface $user
   *
   * @return void
   */
  protected function updateUserRoles(UserInterface $user) {
    $roles = $this->calculateUserRoles($user);
    // Boolean used to mitigate unnecessary saves.
    $needs_save = FALSE;

    foreach ($roles as $role => $value) {
      if ($role === $value && !$user->hasRole($role)) {
        $user->addRole($role);
        $needs_save = TRUE;
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
   * Calculate proper global user roles for current user according to group memberships.
   *
   * @param \Drupal\user\UserInterface $user
   *
   * @return array
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
      $roles = array_merge($roles, $swroles);
    }

    if (empty($roles)) {
      return self::$defaultRoles;
    }
    return $roles;
  }

  /**
   * @param $type
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
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
      GroupMembershipChanged::EVENT_NAME => ['onGroupMembershipChange']
    ];
  }

}
