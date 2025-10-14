<?php

declare(strict_types=1);

namespace Drupal\service_manual_workflow;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/**
 * Handles moderation transitions for entity translations in Drupal.
 */
final class ModerationTransition {

  /**
   * The storage handler for the entity.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $storage;

  /**
   * Constructs a new instance of the class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountProxyInterface $accountProxy
   *   The account proxy service.
   *
   * @return void
   *   Does not return a value.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly AccountProxyInterface $accountProxy,
  ) {
    $this->storage = $this->entityTypeManager->getStorage('node');
  }

  /**
   * Marks all translations as outdated when the default translation is changed.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node whose translations are to be set as outdated.
   * @param string $message
   *   Optional message explaining the reason for the status update.
   *
   * @return void
   *   Does not return any value.
   */
  public function setServiceOutdated(NodeInterface $node, string $message = '') : void {
    // Move all translations to outdated when default translation is changed.
    $update_default_translation = FALSE;
    if ($node->isDefaultTranslation()) {
      if ($node->moderation_state->value !== 'outdated') {
        $update_default_translation = TRUE;
      }
      $this->setModerationStateToTranslations($node, 'outdated', $message, $update_default_translation);
    }
  }

  /**
   * Sets the moderation state for all translations of a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity whose translations' moderation states need to be updated.
   * @param string $state
   *   The moderation state to apply to the node translations.
   * @param string $message
   *   Optional. A message providing additional context
   *   about the moderation state update.
   * @param bool $update_default_translation
   *   Optional. Whether to update the default translation or not.
   *   Default is FALSE.
   *
   * @return void
   *   This method does not return a value.
   */
  public function setModerationStateToTranslations(NodeInterface $node, $state, string $message = '', bool $update_default_translation = FALSE) {
    $languages = $node->getTranslationLanguages();
    foreach ($languages as $language) {
      if ($update_default_translation === FALSE && $node->language()->getId() === $language->getId()) {
        continue;
      }
      $translation = $node->getTranslation($language->getId());
      $this->setNodeModerationState($translation, $state, $message);
    }
  }

  /**
   * Updates the moderation state of a node and saves a new revision.
   *
   * This method ensures the node is updated to the latest revision and modifies
   * the moderation state. It also optionally logs a message and updates
   * metadata related to the revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity whose moderation state is to be updated.
   * @param string $state
   *   The new moderation state to be set.
   * @param string $message
   *   (optional) A log message to describe the revision.
   *   Defaults to an empty string.
   *
   * @return void
   *   No value is returned.
   */
  public function setNodeModerationState(NodeInterface $node, $state, string $message = '') {
    // Ensure node is the latest revision.
    if (!$node->isLatestRevision()) {
      $vid = $this->storage->getLatestRevisionId($node->id());
      $node = $this->storage->loadRevision($vid);
    }

    // If moderation state already is 'outdated', do nothing.
    if ($node->moderation_state->value === $state) {
      return;
    }

    if (!$node->isNewRevision()) {
      $node->setNewRevision();
    }

    if ($node->isDefaultTranslation()) {
      $node->setRevisionUserId($this->getCurrentUserId());
      $node->setChangedTime($this->time->getRequestTime());
    }

    if (!empty($message)) {
      $node->setRevisionLogMessage($message);
    }

    $node->setRevisionUserId($this->getCurrentUserId());
    $node->setRevisionCreationTime($this->time->getRequestTime());
    $node->setRevisionTranslationAffected(TRUE);
    $node->set('moderation_state', $state);
    $node->save();
  }

  /**
   * Retrieves the current user ID. Defaults to 1 if user id is 0.
   *
   * @return int
   *   Returns the current user's ID or 1 if the ID is 0.
   */
  private function getCurrentUserId() {
    if ($this->accountProxy->id() == 0) {
      return 1;
    }
    return $this->accountProxy->id();
  }

}
