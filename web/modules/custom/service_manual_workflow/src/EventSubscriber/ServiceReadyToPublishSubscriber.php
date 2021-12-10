<?php

namespace Drupal\service_manual_workflow\EventSubscriber;

use Drupal\content_moderation\Entity\ContentModerationStateInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\gcontent_moderation\GroupStateTransitionValidation;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Drupal\service_manual_workflow\ContentGroupService;
use Drupal\service_manual_workflow\Event\ServiceModerationEvent;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Entity\EntityInterface;
use Drupal\message\Entity\Message;

/**
 * Service manual workflow event subscriber.
 */
class ServiceReadyToPublishSubscriber implements EventSubscriberInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  protected $entityTypeManager;

  protected $contentGroupService;

  protected $stateTransitionValidation;

  const MESSAGE_TEMPLATE = 'group_ready_to_publish_notificat';

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger, EntityTypeManager $entityTypeManager, ContentGroupService $contentGroupService, GroupStateTransitionValidation $stateTransitionValidation) {
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
    $this->contentGroupService = $contentGroupService;
    $this->stateTransitionValidation = $stateTransitionValidation;
  }

  /**
   * @param \Drupal\service_manual_workflow\Event\ServiceModerationEvent $event
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function draftToReadyToPublish(ServiceModerationEvent $event) {
    $account = $event->account;
    $state = $event->moderation_state;

    $storage = $this->entityTypeManager->getStorage($state->content_entity_type_id->value);
    $entity = $storage->load($state->content_entity_id->value);

    if (!$this->notifyGroupAdministration($account, $entity)) {
      return;
    }


    $accounts = $this->getEntityGroupAdministration($entity);

    foreach ($accounts as $user) {
      $this->dispatchMessage($entity, $user);
    }

    $this->messenger->addStatus(__FUNCTION__);
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return array
   */
  protected function getEntityGroupAdministration(EntityInterface $entity) : array {
    $accounts = [];
    $groups = $this->contentGroupService->getGroupsWithEntity($entity);

    if (empty($groups)) {
      return $accounts;
    }

    $group = reset($groups);
    foreach ($group->getMembers() as $key => $member) {
      $account = $member->getGroupContent()->getEntity();
      $allowed = $this->stateTransitionValidation->allowedTransitions($account, $entity, [$group]);
      if (empty($allowed['publish'])) {
        continue;
      }
      $accounts[$account->id()] = $account;
    }

    return $accounts;
  }

  /**
   * Message dispatcher.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   * @param \Drupal\user\UserInterface $account
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function dispatchMessage(EntityInterface $node, UserInterface $account) {
    $message = Message::create(['template' => self::MESSAGE_TEMPLATE, 'uid' => $account->id()]);
    $message->set('field_node', $node);
    $message->set('field_user', $account);
    $message->save();
//    $notifier = \Drupal::service('message_notify.sender');
    //$notifier->send($message);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'service_manual_workflow.draft.to.ready_to_publish'=> ['draftToReadyToPublish'],
    ];
  }

  protected function notifyGroupAdministration(AccountProxyInterface $account, EntityInterface $node) {
    $valid_transitions = $this->stateTransitionValidation->getValidTransitions($node, $account);
    return empty($valid_transitions['publish']);
  }

}
