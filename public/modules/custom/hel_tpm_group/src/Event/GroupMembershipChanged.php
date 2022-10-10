<?php

namespace Drupal\hel_tpm_group\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\group\Entity\GroupContentInterface;

class GroupMembershipChanged extends Event {

  public const EVENT_NAME = 'hel_tpm_group.group.membership.created';

  public $group_content;

  public function __construct(GroupContentInterface $group_content) {
    $this->group_content = $group_content;
  }

}