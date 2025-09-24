<?php

namespace Drupal\service_manual_workflow\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Event that is fired when a user logs in.
 */
class SetServiceOutdatedEvent extends Event {

  /**
   * Node interface.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * User account object.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|\Drupal\user\UserInterface
   */
  protected $account;

  /**
   * Flag to indicate whether to force update translations.
   *
   * @var bool
   */
  private bool $forceUpdateTranslations;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param \Drupal\Core\Session\UserInterface|null $account
   *   (optional) The user account associated with the operation.
   *   Defaults to NULL.
   * @param bool $force_update_translations
   *   (optional) Whether to force update translations. Defaults to FALSE.
   * @param string $message
   *   (optional) A message for the operation. Defaults to an empty string.
   *
   * @return void
   *   Void.
   */
  public function __construct(NodeInterface $node, ?UserInterface $account = NULL, bool $force_update_translations = FALSE, string $message = '') {
    $this->node = $node;
    $this->account = $account;
    $this->forceUpdateTranslations = $force_update_translations;
    $this->message = $message;
  }

  /**
   * Retrieves the node object.
   *
   * @return \Drupal\node\NodeInterface
   *   Node entity object.
   */
  public function getNode() : NodeInterface {
    return $this->node;
  }

  /**
   * Determines if translations are forced to update.
   *
   * @return bool
   *   TRUE if translations are forced to update, FALSE otherwise.
   */
  public function getForcedUpdateTranslations() : bool {
    return $this->forceUpdateTranslations;
  }

  /**
   * Retrieves the message.
   *
   * @return string
   *   The message string.
   */
  public function getMessage(): string {
    return $this->message;
  }

  /**
   * Getter for account.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   Account proxy object.
   */
  public function getAccount() : UserInterface {
    return !empty($this->account) ? $this->account : \Drupal::currentUser()->getAccount();
  }

}
