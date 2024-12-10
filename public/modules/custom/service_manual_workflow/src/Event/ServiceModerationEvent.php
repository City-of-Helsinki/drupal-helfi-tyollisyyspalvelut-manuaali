<?php

namespace Drupal\service_manual_workflow\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\content_moderation\Entity\ContentModerationStateInterface;

/**
 * Event that is fired when a user logs in.
 */
class ServiceModerationEvent extends Event {

  /**
   * The user account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $moderationState;

  /**
   * User account object.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\Drupal\user\UserInterface
   */
  protected $account;

  /**
   * Constructs the object.
   *
   * @param \Drupal\content_moderation\Entity\ContentModerationStateInterface $state
   *   Content moderation state object.
   * @param \Drupal\user\UserInterface $account
   *   The account of the user logged in.
   */
  public function __construct(ContentModerationStateInterface $state, AccountProxyInterface $account) {
    $this->moderationState = $state;
    $this->account = $account;
  }

  /**
   * Getter for moderation_state.
   *
   * @return \Drupal\content_moderation\Entity\ContentModerationStateInterface
   *   Moderation state object.
   */
  public function getModerationState() : ContentModerationStateInterface {
    return $this->moderationState;
  }

  /**
   * Getter for account.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   Account proxy object.
   */
  public function getAccount() : AccountProxyInterface {
    return $this->account;
  }

}
