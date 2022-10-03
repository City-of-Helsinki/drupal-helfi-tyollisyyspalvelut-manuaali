<?php

namespace Drupal\hel_tpm_group\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\group\Entity\GroupRole;

class GroupMembershipCreated extends Event {

  public const EVENT_NAME = 'hel_tpm_group.group.membership.created';

  public $group_role;

  public function __construct(GroupRole $group_role) {
    $this->group_role = $group_role;
  }

}