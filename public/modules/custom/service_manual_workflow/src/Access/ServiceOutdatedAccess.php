<?php

namespace Drupal\service_manual_workflow\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\node\NodeInterface;

/**
 * Service outdated access control handler.
 */
class ServiceOutdatedAccess {

  /**
   * Group state transition validator service.
   *
   * @var \Drupal\gcontent_moderation\GroupStateTransitionValidation
   */
  protected $groupStateTransitionValidator;

  /**
   * Moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * Constructor for service outdated access.
   *
   * @param \Drupal\gcontent_moderation\GroupStateTransitionValidation $group_state_transition_validator
   *   Group state transition validator.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   Moderation information service.
   */
  public function __construct(StateTransitionValidationInterface $group_state_transition_validator, ModerationInformationInterface $moderation_information) {
    $this->groupStateTransitionValidator = $group_state_transition_validator;
    $this->moderationInformation = $moderation_information;
  }

  /**
   * Access callback for service outdated access.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node object.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   User account object.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden|\Drupal\Core\Access\AccessResultNeutral
   *   Returns access result object according user access.
   */
  public function access(NodeInterface $node, AccountInterface $user) {
    if (!$this->moderationInformation->isModeratedEntity($node)) {
      return AccessResult::forbidden();
    }
    $moderation_state = $node->get('moderation_state')->value;
    if ($moderation_state === 'outdated') {
      return AccessResult::forbidden();
    }
    return $this->transitionValid($node, $user, 'outdated');
  }

  /**
   * Transition validation handler.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node object.
   * @param \Drupal\user\UserInterface $user
   *   User account object.
   * @param string $transition
   *   Transition which is validated.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   Returns access result object.
   */
  protected function transitionValid(NodeInterface $node, AccountInterface $user, $transition) {
    $transitions = $this->groupStateTransitionValidator->getValidTransitions($node, $user);
    if (in_array($transition, array_keys($transitions))) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
