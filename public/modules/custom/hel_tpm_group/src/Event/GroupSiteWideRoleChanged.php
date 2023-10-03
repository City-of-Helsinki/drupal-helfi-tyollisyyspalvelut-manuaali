<?php

namespace Drupal\hel_tpm_group\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\group\Entity\GroupRole;

/**
 * Group site wide role changed event.
 */
class GroupSiteWideRoleChanged extends Event {

  public const EVENT_NAME = 'hel_tpm_group.site_wide_role_changed';

  /**
   * Group role object.
   *
   * @var \Drupal\group\Entity\GroupRole
   */
  public $groupRole;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\group\Entity\GroupRole $group_role
   *   Changed group role.
   */
  public function __construct(GroupRole $group_role) {
    $this->groupRole = $group_role;
  }

}
