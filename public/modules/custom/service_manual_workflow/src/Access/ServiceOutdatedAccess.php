<?php
namespace Drupal\service_manual_workflow\Access;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\gcontent_moderation\GroupStateTransitionValidation;
use Drupal\node\NodeInterface;
use Drupal\node\Plugin\views\filter\Access;
use Drupal\user\UserInterface;

class ServiceOutdatedAccess {

  /**
   * @var \Drupal\gcontent_moderation\GroupStateTransitionValidation
   */
  protected $groupStateTransitionValidator;

  /**
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * @param \Drupal\gcontent_moderation\GroupStateTransitionValidation $group_state_transition_validator
   */
  public function __construct(GroupStateTransitionValidation $group_state_transition_validator, ModerationInformationInterface $moderation_information) {
    $this->groupStateTransitionValidator = $group_state_transition_validator;
    $this->moderationInformation = $moderation_information;
  }

  /**
   * @param \Drupal\node\NodeInterface $node
   * @param \Drupal\Core\Session\AccountInterface $user
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden|\Drupal\Core\Access\AccessResultNeutral
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
   * @param \Drupal\node\NodeInterface $node
   * @param \Drupal\user\UserInterface $user
   * @param $transition
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   */
  protected function transitionValid(NodeInterface $node, AccountInterface $user, $transition) {
    $transitions = $this->groupStateTransitionValidator->getValidTransitions($node, $user);
    if (in_array($transition, array_keys($transitions))) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }
}