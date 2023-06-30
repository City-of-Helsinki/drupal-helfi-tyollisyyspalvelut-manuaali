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
use Drupal\message_notify\MessageNotifier;
use Drupal\node\NodeInterface;
use Drupal\service_manual_workflow\ContentGroupService;
use Drupal\service_manual_workflow\Event\ServiceModerationEvent;
use Drupal\service_manual_workflow\ServiceNotificationTrait;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\message\Entity\Message;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Service manual workflow event subscriber.
 */
class ServiceStateChangedNotificationSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  use ServiceNotificationTrait;

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

  protected $messageSender;

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
  public function __construct(MessengerInterface $messenger, EntityTypeManager $entityTypeManager, ContentGroupService $contentGroupService, GroupStateTransitionValidation $stateTransitionValidation, RouteMatchInterface $routeMatch, AccountProxyInterface $currentUser, MessageNotifier $messageSender) {
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
    $this->contentGroupService = $contentGroupService;
    $this->stateTransitionValidation = $stateTransitionValidation;
    $this->routeMatch = $routeMatch;
    $this->currentUser = $currentUser;
    $this->messageSender = $messageSender;
  }

  /**
   * @param \Drupal\service_manual_workflow\Event\ServiceModerationEvent $event
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function draftToReadyToPublish(ServiceModerationEvent $event) {
    $account = $event->getAccount();
    $state = $event->getModerationState();
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
      $this->dispatchMessage($entity, $user,'group_ready_to_publish_notificat');
    }
    $this->messenger->addStatus($this->t('Notified @group administration', ['@group' => $group->label()]));
  }

  /**
   * Service from ready to publish to published subscriber event.
   *
   * @param \Drupal\service_manual_workflow\Event\ServiceModerationEvent $event
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function readyToPublishToPublished(ServiceModerationEvent $event) {
    $state = $event->getModerationState();
    $storage = $this->entityTypeManager->getStorage($state->content_entity_type_id->value);
    $entity = $storage->load($state->content_entity_id->value);

    if (!$this->notifyServiceProvider($entity)) {
      return;
    }

    $service_provider_updatee = $entity->get('field_service_provider_updatee')->entity;
    // Send content published notification to service provider updatee.
    $this->dispatchMessage($entity, $service_provider_updatee, 'content_has_been_published');
  }

  /**
   * Check if service provider should be notified or not.
   *
   * @param \Drupal\node\NodeInterface $entity
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function notifyServiceProvider(NodeInterface $entity) {
    $current_uid = $this->currentUser->id();
    $service_provider_updatee = $entity->get('field_service_provider_updatee')->entity;
    // If service provider is the same as user publishing service don't send message.
    if ($current_uid === $service_provider_updatee->id()) {
      return FALSE;
    }
    return TRUE;
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
   * Message dispatcher.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   * @param \Drupal\user\UserInterface $account
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function dispatchMessage(EntityInterface $node, UserInterface $account, $message_template) {
    $current_user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $message = Message::create(['template' => $message_template, 'uid' => $account->id()]);
    $message->set('field_node', $node);
    $message->set('field_user', $account);
    $message->set('field_message_author', $current_user);
    $message->save();
    $this->messageSender->send($message);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'service_manual_workflow.draft.to.ready_to_publish' => ['draftToReadyToPublish'],
      'service_manual_workflow.ready_to_publish.to.published' => ['readyToPublishToPublished']
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

}
