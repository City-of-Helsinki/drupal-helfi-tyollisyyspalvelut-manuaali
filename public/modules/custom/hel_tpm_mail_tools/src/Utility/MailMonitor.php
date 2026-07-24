<?php

namespace Drupal\hel_tpm_mail_tools\Utility;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Monitors email sending patterns to prevent excessive duplication.
 *
 * This class tracks the frequency and content of sent emails to ensure
 * compliance with the defined constraints for delivery.
 */
class MailMonitor {

  /**
   * Number of identical mails allowed in the time window.
   */
  private const IDENTICAL_MAIL_LIMIT = 5;


  /**
   * Time window in seconds.
   */
  private const IDENTICAL_MAIL_WINDOW = 120 * 60;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    $this->time = $time;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Determines if an email has been sent too many times.
   *
   * This method checks if the provided email instance has exceeded the
   * predefined sending limit by comparing it against previously sent emails.
   *
   * @param \EmailInterface $email
   *   An instance of the email to check send count.
   *
   * @return bool
   *   Returns true if the email has been sent too many times, otherwise false.
   */
  public function mailSentTooManyTimes(EmailInterface $email): bool {
    return $this->hasIdenticalMailBeenSentTooManyTimes($email);
  }

  /**
   * Checks whether identical mail was sent to the same user too many times.
   */
  private function hasIdenticalMailBeenSentTooManyTimes(EmailInterface $email): bool {
    $created_after = $this->time->getRequestTime() - self::IDENTICAL_MAIL_WINDOW;

    $query = $this->entityTypeManager
      ->getStorage('symfony_mailer_log')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('to', $this->getEmailTo($email), 'IN')
      ->condition('created', $created_after, '>=')
      ->condition('type', $email->getType())
      ->condition('subject', $email->getSubject())
      ->condition('text_body', $email->getTextBody());

    if ($email->getSubType() !== NULL) {
      $query->condition('sub_type', $email->getSubType());
    }
    else {
      $query->notExists('sub_type');
    }

    $ids = $query->range(0, self::IDENTICAL_MAIL_LIMIT)->execute();

    return count($ids) >= self::IDENTICAL_MAIL_LIMIT;
  }

  /**
   * Retrieves an array of email addresses from the provided email object.
   *
   * This method extracts all the recipient email addresses from the given
   * EmailInterface object by iterating over its "to" field.
   *
   * @param \EmailInterface $email
   *   The email object from which to retrieve recipient addresses.
   *
   * @return array|null
   *   An array of email addresses or null if no addresses are found.
   */
  private function getEmailTo(EmailInterface $email):? array {
    $result = [];
    foreach ($email->getTo() as $address) {
      $result[] = $address->getEmail();
    }
    return $result;
  }

}
