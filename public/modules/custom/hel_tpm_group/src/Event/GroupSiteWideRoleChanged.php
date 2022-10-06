<?php

namespace Drupal\hel_tpm_group\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\group\Entity\GroupRole;

class GroupSiteWideRoleChanged extends Event {

  public const EVENT_NAME = 'hel_tpm_group.site_wide_role_changed';

  public $group_role;

  public function __construct(GroupRole $group_role) {
    $this->group_role = $group_role;
  }

}