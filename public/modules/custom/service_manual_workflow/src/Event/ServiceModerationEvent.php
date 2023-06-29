<?php

namespace Drupal\service_manual_workflow\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Session\AccountInterface;
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
  protected $moderation_state;
  protected $account;

  /**
   * Constructs the object.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account of the user logged in.
   */
  public function __construct(ContentModerationStateInterface $state, AccountProxyInterface $account) {
    $this->moderation_state = $state;
    $this->account = $account;
  }

  public function getModerationState() : ContentModerationStateInterface {
    return $this->moderation_state;
  }

  public function getAccount() : AccountProxyInterface {
    return $this->account;
  }
}
