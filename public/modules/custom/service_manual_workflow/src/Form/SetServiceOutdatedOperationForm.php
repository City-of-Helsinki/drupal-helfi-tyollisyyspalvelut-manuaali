<?php

namespace Drupal\service_manual_workflow\Form;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\service_manual_workflow\Access\ServiceOutdatedAccess;
use Drupal\service_manual_workflow\ModerationTransition;
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
   * Moderation transition manager.
   *
   * @var \Drupal\service_manual_workflow\ModerationTransition
   */
  private ModerationTransition $moderationTransition;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManager $entity_type_manager, ServiceOutdatedAccess $service_outdated_access, ModerationTransition $moderation_transition) {
    $this->entityTypeManager = $entity_type_manager;
    $this->serviceOutdatedAccess = $service_outdated_access;
    $this->moderationTransition = $moderation_transition;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('service_manual_workflow.set_outdated_access'),
      $container->get('service_manual_workflow.moderation_transition')
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
    $all_translations_outdated = FALSE;
    if ($form_state->getValue('all_translations_outdated')) {
      $all_translations_outdated = TRUE;
    }

    $this->setNodeOutdated($node, $all_translations_outdated);

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Sets the specified node or its translations to an outdated state.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to set as outdated.
   * @param bool $set_all_translations_outdated
   *   Whether to set all translations of the node as outdated.
   *
   * @return void
   *   Returns nothing.
   */
  protected function setNodeOutdated(NodeInterface $node, bool $set_all_translations_outdated) {
    if (!$node->isDefaultTranslation() && $set_all_translations_outdated) {
      $node = $node->getTranslation('x-default');
    }
    $this->moderationTransition->setNodeModerationState($node, 'outdated', 'Outdated with outdate service form');
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
