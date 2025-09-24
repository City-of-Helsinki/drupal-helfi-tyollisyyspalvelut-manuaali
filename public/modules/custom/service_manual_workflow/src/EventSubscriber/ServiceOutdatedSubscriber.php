<?php

declare(strict_types=1);

namespace Drupal\service_manual_workflow\EventSubscriber;

use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Component\Datetime\Time;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\service_manual_workflow\Event\SetServiceOutdatedEvent;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Service outdated event subscriber.
 */
final class ServiceOutdatedSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Node storage interface.
   *
   * @var \Drupal\Core\Entity\RevisionableStorageInterface
   */
  private RevisionableStorageInterface $storage;

  /**
   * Constructs a ServiceOutdatedSubscriber object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Time $time,
  ) {
    $this->storage = $this->entityTypeManager->getStorage('node');
  }

  /**
   * Marks the service as outdated for the given event.
   *
   * @param \Drupal\service_manual_workflow\Event\SetServiceOutdatedEvent $event
   *   The event that triggers the service outdated process. It contains the
   *   affected node and context, such as forced update of translations.
   *
   * @return void
   *   This method does not return any value.
   */
  public function serviceOutdated(SetServiceOutdatedEvent $event) : void {
    $node = $event->getNode();
    $message = $event->getMessage();

    // Ensure node is the latest revision.
    if (!$node->isLatestRevision()) {
      $vid = $this->storage->getLatestRevisionId($node->id());
      $node = $node->loadRevision($vid);
    }

    if ($node->isDefaultTranslation() || $event->getForcedUpdateTranslations()) {
      $languages = $node->getTranslationLanguages();
      foreach ($languages as $language) {
        $translation = $node->getTranslation($language->getId());
        $this->setServiceOutDated($translation, $event->getAccount(), $message);
      }
    }
    else {
      $this->setServiceOutDated($node, $event->getAccount(), $message);
    }
  }

  /**
   * Sets a service to the "outdated" state.
   *
   * Updates the node's moderation state, revision information, and marks
   * the node as affected by translation changes.
   *
   * @param \NodeInterface $node
   *   The node entity to be marked as outdated.
   * @param \UserInterface $account
   *   The user account responsible for the revision.
   * @param string $message
   *   (optional) The revision log message. Defaults to an empty string.
   *
   * @return void
   *   Void.
   */
  protected function setServiceOutDated(NodeInterface $node, UserInterface $account, string $message = ''): void {
    if (!$node->isNewRevision()) {
      $node->setNewRevision();
    }

    if ($node->isDefaultTranslation()) {
      $node->setRevisionUserId($account->id());
      if (!empty($message)) {
        $node->setRevisionLogMessage($this->t('Set automatically to outdated'));
      }
      $node->setRevisionCreationTime($this->time->getRequestTime());
    }

    $node->setRevisionTranslationAffected(TRUE);
    $node->set('moderation_state', 'outdated');
    $node->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'service_manual_workflow.set_service_outdated' => ['serviceOutdated'],
    ];
  }

}
