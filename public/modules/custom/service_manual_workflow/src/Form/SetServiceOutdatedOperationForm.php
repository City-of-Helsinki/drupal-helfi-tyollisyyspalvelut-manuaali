<?php

namespace Drupal\service_manual_workflow\Form;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\service_manual_workflow\Access\ServiceOutdatedAccess;
use Drupal\service_manual_workflow\Event\SetServiceOutdatedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for set service outdated operation.
 */
class SetServiceOutdatedOperationForm extends ConfirmFormBase {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Service outdated access service.
   *
   * @var \Drupal\service_manual_workflow\Access\ServiceOutdatedAccess
   */
  protected $serviceOutdatedAccess;

  /**
   * Node interface.
   *
   * @var \Drupal\node\NodeInterface|mixed
   */
  private mixed $node;

  /**
   * Event dispatcher.
   *
   * @var \Psr\EventDispatcher\EventDispatcherInterface
   */
  private EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManager $entity_type_manager, ServiceOutdatedAccess $service_outdated_access, EventDispatcherInterface $event_dispatcher) {
    $this->entityTypeManager = $entity_type_manager;
    $this->serviceOutdatedAccess = $service_outdated_access;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('service_manual_workflow.set_outdated_access'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Confirm changing @node to Outdated', ['@node' => $this->node->getTitle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Are you sure you want to change @node as Outdated and unpublish it?', [
      '@node' => $this->node->getTitle(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'service_workflow_set_outdated_operation_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form_state->getStorage()['node'];

    // Only current language is set as outdated.
    $this->node = $node;
    $all_translations_outadated = FALSE;
    if ($form_state->getValue('all_translations_outdated')) {
      $all_translations_outadated = TRUE;
    }

    $event = new SetServiceOutdatedEvent($node, NULL, $all_translations_outadated);
    $this->eventDispatcher->dispatch($event, 'service_manual_workflow.set_service_outdated');

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Set node outdated method.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node object.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setNodeOutdated(NodeInterface $node) {
    $node->set('moderation_state', 'outdated');
    $node->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    if (!$node instanceof NodeInterface) {
      return [];
    }
    $form_state->setStorage(['node' => $node]);
    $this->node = $node;
    $form = parent::buildForm($form, $form_state);
    $form['all_translations_outdated'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set all translations as outdated'),
    ];
    return $form;
  }

  /**
   * Access method to check if user has access to node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node object.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   Returns access result object.
   */
  public function access(NodeInterface $node) {
    return $this->serviceOutdatedAccess->access($node, $this->currentUser());
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    if ($this->node->getEntityType()->hasLinkTemplate('collection')) {
      return new Url('entity.' . $this->node->getEntityTypeId() . '.collection');
    }
    else {
      return new Url('<front>');
    }
  }

}
