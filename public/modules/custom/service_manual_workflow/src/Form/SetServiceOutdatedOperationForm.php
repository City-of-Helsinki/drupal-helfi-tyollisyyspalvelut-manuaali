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
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    if (!$node instanceof NodeInterface) {
      return [];
    }
    $form_state->setStorage(['node' => $node]);
    $this->node = $node;
    $form = parent::buildForm($form, $form_state);
    if ($this->node->getTranslationLanguages(FALSE)) {
      $form['all_translations_outdated'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Set all translations as outdated'),
        '#disabled' => $this->node->isDefaultTranslation(),
        '#default_value' => $this->node->isDefaultTranslation(),
        '#ajax' => [
          'wrapper' => 'description-wrapper',
          'event' => 'change',
          'callback' => '::updateDescriptionAjax',
        ],
      ];
    }
    $form['description']['#prefix'] = '<div id="description-wrapper">';
    $form['description']['#suffix'] = '</div>';
    $form['description']['#markup'] = $this->getDescriptionDynamic($form, $form_state);
    return $form;
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
   * Generates a dynamic description message based on node state and form input.
   *
   * @param array $form
   *   The form structure array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form.
   *
   * @return string
   *   The generated description message.
   */
  public function getDescriptionDynamic(array $form, FormStateInterface $form_state) {
    $default_message = $this->t("You are about to change @node to Outdated and unpublished. After this the service won't show as published in Palvelumanuaali selections.", ['@node' => $this->node->getTitle()]);
    $additional_message = $this->t('All translations will be moved to Outdated state.');
    $message = sprintf('%s', $default_message);

    if ($this->node->getTranslationLanguages(FALSE)) {
      $show_additional = $form_state->getValue('all_translations_outdated');
      if (empty($show_additional)) {
        $show_additional = $form['all_translations_outdated']['#default_value'];
      }
      if ($show_additional) {
        $message = sprintf('%s %s', $default_message, $additional_message);
      }
    }
    return $message;
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
   * Updates and retrieves the description from the form.
   *
   * @param array $form
   *   The form structure to extract the description from.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return mixed
   *   The description element of the form.
   */
  public function updateDescriptionAjax($form, $form_state) {
    return $form['description'];
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
