<?php

namespace Drupal\service_manual_workflow\EventSubscriber;

use Drupal;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\gcontent_moderation\GroupStateTransitionValidation;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Drupal\service_manual_workflow\ContentGroupService;
use Drupal\service_manual_workflow\Event\ServiceModerationEvent;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\message\Entity\Message;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Service manual workflow event subscriber.
 */
class ServiceReadyToPublishSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\service_manual_workflow\ContentGroupService
   */
  protected $contentGroupService;

  /**
   * @var \Drupal\gcontent_moderation\GroupStateTransitionValidation
   */
  protected $stateTransitionValidation;

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  protected $currentUser;

  const MESSAGE_TEMPLATE = 'group_ready_to_publish_notificat';

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *  Messenger.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *  Entity type manager.
   *
   * @param \Drupal\service_manual_workflow\ContentGroupService $contentGroupService
   *  Custom service for content group stuff.
   *
   * @param \Drupal\gcontent_moderation\GroupStateTransitionValidation $stateTransitionValidation
   *  Group content state transition validator.
   *
   */
  public function __construct(MessengerInterface $messenger, EntityTypeManager $entityTypeManager, ContentGroupService $contentGroupService, GroupStateTransitionValidation $stateTransitionValidation, RouteMatchInterface $routeMatch, AccountProxyInterface $currentUser) {
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
    $this->contentGroupService = $contentGroupService;
    $this->stateTransitionValidation = $stateTransitionValidation;
    $this->routeMatch = $routeMatch;
    $this->currentUser = $currentUser;
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

    // Get content group.
    $group = $this->getGroup($entity);
    if (empty($group)) {
      return;
    }

    $accounts = $this->getUsersToNotify($entity);

    if (empty($accounts)) {
      $this->messenger->addStatus($this->t('Users with publish permissions not found. Please contact site administration.', ['@group' => $group->label()]));
    }

    // Dispatch messages to group administration.
    foreach ($accounts as $user) {
      $this->dispatchMessage($entity, $user);
    }

    $this->messenger->addStatus($this->t('Notified @group administration', ['@group' => $group->label()]));
  }

  /**
   * Fetch available users for sending notification.
   *
   * @param \Drupal\node\NodeInterface $entity
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getUsersToNotify(NodeInterface $entity) : array {
    // Return owner(s) if service has one.
    $users = $this->getServiceOwner($entity);
    if (!empty($users)) {
      return $users;
    }

    // Send message to organization admins in group.
    $users = $this->getPublishersFromEntityGroup($entity);
    if (!empty($users)) {
      return $users;
    }

    return [];
  }

  /**
   * @param \Drupal\node\NodeInterface $entity
   *
   * @return array
   */
  protected function getPublishersFromEntityGroup(NodeInterface $entity) : array {
    $group = $this->getGroup($entity);
    if (empty($group)) {
      return [];
    }
    return $this->getEntityGroupAdministration($entity, $group);
  }

  /**
   * @param \Drupal\node\NodeInterface $entity
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getServiceOwner(NodeInterface $entity) : array {
    $user = [];
    $group_contents = $this->entityTypeManager->getStorage('group_content')->loadByProperties(['entity_id' => $entity->id()]);

    // Service is not part of any group.
    if (empty($group_contents)) {
      return $user;
    }

    // Get all service owners in to an array.
    foreach ($group_contents as $group_content) {
      $owner = $group_content->get('field_service_owner')->referencedEntities();
      $user[] = reset($owner);
    }

    return $user;
  }

  /**
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *
   * @return array|false|mixed
   */
  protected function getGroup(ContentEntityInterface $entity) {
    $groups = $this->contentGroupService->getGroupsWithEntity($entity);

    if (!empty($groups)) {
      $group = reset($groups);
    }
    else {
      $group = $this->getGroupFromRoute();
    }

    if (empty($group)) {
      return [];
    }

    return $group;
  }

  /**
   * Get all group users with permission to create publish transition.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   * @param \Drupal\group\Entity\GroupInterface $group
   *
   * @return array
   */
  protected function getEntityGroupAdministration(ContentEntityInterface $entity, GroupInterface $group) : array {
    $accounts = [];

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
    $message = Message::create(['template' => self::MESSAGE_TEMPLATE, 'uid' => $this->currentUser->id()]);
    $message->set('field_node', $node);
    $message->set('field_user', $account);
    $message->save();
    $notifier = Drupal::service('message_notify.sender');
    $notifier->send($message);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'service_manual_workflow.draft.to.ready_to_publish'=> ['draftToReadyToPublish'],
    ];
  }

  /**
   * Check if current user has publish permission or not.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   * @param \Drupal\Core\Entity\ContentEntityInterface $node
   *
   * @return bool
   */
  protected function notifyGroupAdministration(AccountProxyInterface $account, ContentEntityInterface $node) {
    $valid_transitions = $this->stateTransitionValidation->getValidTransitions($node, $account);
    return empty($valid_transitions['publish']);
  }

  /**
   * Get the group from the current route match.
   *
   * @return bool|\Drupal\group\Entity\GroupInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function getGroupFromRoute() {
    $parameters = $this->routeMatch->getParameters()->all();
    if (empty($parameters['group']) || !$parameters['group'] instanceof GroupInterface) {
      return FALSE;
    }
    return $parameters['group'];
  }
}
