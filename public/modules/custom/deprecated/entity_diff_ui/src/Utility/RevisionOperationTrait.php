<?php

namespace Drupal\entity_diff_ui\Utility;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Entity revision operation helper trait.
 */
trait RevisionOperationTrait {

  use StringTranslationTrait;

  /**
   * Reivision delete helper.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $revision_storage
   *   The entity storage object of the revision.
   * @param \Drupal\Core\Entity\EntityStorageInterface $bundle_storage
   *   The bundle storage object of the revision.
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   The revision to revert.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger object to write a Drupal log.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger object to set a message in the page.
   */
  public function revisionDelete(EntityStorageInterface $revision_storage, EntityStorageInterface $bundle_storage, ContentEntityInterface $revision, LoggerInterface $logger, MessengerInterface $messenger) {
    $revision_storage->deleteRevision($revision->getRevisionId());

    $logger->notice('@type: deleted %title revision %revision.', [
      '@type' => $revision->bundle(),
      '%title' => $revision->label(),
      '%revision' => $revision->getRevisionId(),
    ]);
    $bundle_label = $bundle_storage->load($revision->bundle())->label();
    $date_formatter = \Drupal::service('date.formatter');

    $messenger
      ->addStatus($this->t('Revision from %revision-date of @type %title has been deleted.', [
        '%revision-date' => $date_formatter->format($revision->getRevisionCreationTime()),
        '@type' => $bundle_label,
        '%title' => $revision->label(),
      ]));
  }

  /**
   * Reivision revert helper.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $bundle_storage
   *   The bundle storage object of the revision.
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   The revision to revert.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger object to write a Drupal log.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger object to set a message in the page.
   * @param int $uid
   *   The ID of the user who conduct the revert.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object of the operation form.
   */
  public function revisionRevert(EntityStorageInterface $bundle_storage, ContentEntityInterface $revision, LoggerInterface $logger, MessengerInterface $messenger, int $uid, FormStateInterface $form_state) {
    $date_formatter = \Drupal::service('date.formatter');
    $time = \Drupal::service('datetime.time');
    $bundle_label = $bundle_storage->load($revision->bundle())->label();
    // The revision timestamp will be updated when the revision is saved. Keep
    // the original one for the confirmation message.
    $original_revision_timestamp = $revision->getRevisionCreationTime();

    $revision = $this->prepareRevertedRevision($revision, $form_state);
    $revision->revision_log = $this->t('Copy of the revision from %date.', ['%date' => $date_formatter->format($original_revision_timestamp)]);
    $revision->setRevisionUserId($uid);
    $revision->setRevisionCreationTime($time->getRequestTime());
    $revision->setChangedTime($time->getRequestTime());
    $revision->save();

    $logger->notice('@type: reverted %title revision %revision.', [
      '@type' => $revision->bundle(),
      '%title' => $revision->label(),
      '%revision' => $revision->getRevisionId(),
    ]);
    $messenger
      ->addStatus($this->t('@type %title has been reverted to the revision from %revision-date.', [
    // ->getEntityType()->getBundleLabel(),
        '@type' => $bundle_label,
        '%title' => $revision->label(),
        '%revision-date' => $date_formatter->format($original_revision_timestamp),
      ]));
  }

  /**
   * Prepares a revision to be reverted.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   The revision to revert.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object of the operation form.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The prepared revision ready to be stored.
   */
  protected function prepareRevertedRevision(ContentEntityInterface $revision, FormStateInterface $form_state) {
    $revision->setNewRevision();
    $revision->isDefaultRevision(TRUE);

    return $revision;
  }

}
