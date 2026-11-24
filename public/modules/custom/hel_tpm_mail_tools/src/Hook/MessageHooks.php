<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_mail_tools\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for message entities.
 */
class MessageHooks {

  /**
   * Implements hook_message_load().
   *
   * The language a message is rendered in is not persisted by the Message
   * module, so loaded messages render the template text and tokens with an
   * unspecified language. Restore it from the stored langcode so that e.g. the
   * message listing shows the message in the language it was sent in.
   *
   * Only messages whose template has the "keep_sent_language" third-party
   * setting are affected. Other messages keep the Message module default of
   * rendering in the current language.
   *
   * @param \Drupal\message\MessageInterface[] $messages
   *   The loaded messages.
   */
  #[Hook('message_load')]
  public function messageLoad(array $messages): void {
    foreach ($messages as $message) {
      $template = $message->getTemplate();
      if (!$template || !$template->getThirdPartySetting('hel_tpm_mail_tools', 'keep_sent_language', FALSE)) {
        continue;
      }
      $message->setLanguage($message->language()->getId());
    }
  }

}
