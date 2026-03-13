<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_mail_tools\Utility;

use Drupal\hel_tpm_mail_tools\Plugin\Validation\Constraint\PreventMessageConstraint;
use Drupal\message\Entity\Message;
use Drupal\message_notify\MessageNotifier;
use Drupal\user\UserInterface;

/**
 * Helper service to create and send messages with validation.
 */
class MessageSender {

  /**
   * Message notifier service.
   *
   * @var \Drupal\message_notify\MessageNotifier
   */
  protected MessageNotifier $messageNotifier;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\message_notify\MessageNotifier $message_notifier
   *   The message notifier service.
   */
  public function __construct(MessageNotifier $message_notifier) {
    $this->messageNotifier = $message_notifier;
  }

  /**
   * Creates and sends message after validation.
   *
   * @param string $template
   *   The message template.
   * @param \Drupal\user\UserInterface $account
   *   The owner user entity.
   * @param array $fields
   *   Array of arrays holding field keys and field values.
   * @param array|null $context
   *   The key and value of the context object.
   *
   * @return bool
   *   TRUE if mail is sent, FALSE otherwise.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\message_notify\Exception\MessageNotifyException
   */
  public function createAndSend(string $template, UserInterface $account, array $fields, ?array $context = NULL): bool {
    $message = Message::create([
      'template' => $template,
    ]);
    $message->setOwner($account);

    foreach ($fields as $key => $value) {
      $message->set($key, $value);
    }

    if (is_array($context)) {
      $message->addContext($context[0], $context[1]);
    }

    $violations = $message->validate()->getEntityViolations();
    foreach ($violations as $violation) {
      if ($violation->getConstraint() instanceof PreventMessageConstraint) {
        return FALSE;
      }
    }

    $message->save();
    return $this->messageNotifier->send($message);
  }

}
