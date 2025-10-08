<?php

namespace Drupal\service_manual_workflow\EventSubscriber;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\service_manual_workflow\Event\ServiceModerationEvent;
use Drupal\service_manual_workflow\ModerationTransition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Service manual workflow event subscriber.
 */
class ServiceStateArchivedSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Node storage interface.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $storage;

  /**
   * Constructs a new instance of the class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\service_manual_workflow\ModerationTransition $moderationTransition
   *   The moderation transition service.
   *
   * @return void
   *   Returns nothing.
   */
  public function __construct(
    protected EntityTypeManager $entityTypeManager,
    protected ModerationTransition $moderationTransition,
  ) {
    $this->storage = $this->entityTypeManager->getStorage('node');
  }

  /**
   * Handles the service outdated moderation event.
   *
   * @param \Drupal\service_manual_workflow\Event\ServiceModerationEvent $event
   *   The service moderation event containing details about
   *   the moderation state and associated content entity.
   *
   * @return void
   *   Returns nothing.
   */
  public function serviceArchived(ServiceModerationEvent $event) {
    $moderation_state = $event->getModerationState();
    $node = $this->storage->loadRevision($moderation_state->content_entity_revision_id->value);
    $node = $node->getTranslation($moderation_state->language()->getId());
    $this->moderationTransition->setServiceArchived($node, 'Archived by service state subscriber');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'service_manual_workflow.published.to.archived' => ['serviceArchived'],
      'service_manual_workflow.outdated.to.archived' => ['serviceArchived'],
      'service_manual_workflow.draft.to.archived' => ['serviceArchived'],
    ];
  }

}
