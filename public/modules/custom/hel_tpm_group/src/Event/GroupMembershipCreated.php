<?php

namespace Drupal\hel_tpm_group\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\group\Entity\GroupRole;

/**
 * Group membership created event.
 */
class GroupMembershipCreated extends Event {

  public const EVENT_NAME = 'hel_tpm_group.group.membership.created';

  /**
   * Group role object.
   *
   * @var \Drupal\group\Entity\GroupRole
   */
  public $groupRole;

  /**
   * {@inheritdoc}
   */
  public function __construct(GroupRole $group_role) {
    $this->groupRole = $group_role;
  }

}
