<?php

namespace Drupal\hel_tpm_group\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\group\Entity\GroupContentInterface;

/**
 * Group membership changed event.
 */
class GroupMembershipChanged extends Event {

  public const EVENT_NAME = 'hel_tpm_group.group.membership.created';

  /**
   * Group role content.
   *
   * @var \Drupal\group\Entity\GroupContentInterface
   */
  public $groupContent;

  /**
   * {@inheritdoc}
   */
  public function __construct(GroupContentInterface $group_content) {
    $this->groupContent = $group_content;
  }

}
