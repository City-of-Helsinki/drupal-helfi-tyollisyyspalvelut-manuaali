<?php

namespace Drupal\hel_tpm_group\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\group\Entity\GroupContentInterface;

/**
 * Group membership deleted event.
 */
class GroupMembershipDeleted extends Event {

  public const EVENT_NAME = 'hel_tpm_group.group.membership.deleted';

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
