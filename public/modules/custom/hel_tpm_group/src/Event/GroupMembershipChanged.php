<?php

namespace Drupal\hel_tpm_group\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\group\Entity\GroupRelationshipInterface;

/**
 * Group membership changed event.
 */
class GroupMembershipChanged extends Event {

  public const EVENT_NAME = 'hel_tpm_group.group.membership.created';

  /**
   * Group role content.
   *
   * @var \Drupal\group\Entity\GroupRelationshipInterface
   */
  public $groupContent;

  /**
   * {@inheritdoc}
   */
  public function __construct(GroupRelationshipInterface $group_content) {
    $this->groupContent = $group_content;
  }

}
