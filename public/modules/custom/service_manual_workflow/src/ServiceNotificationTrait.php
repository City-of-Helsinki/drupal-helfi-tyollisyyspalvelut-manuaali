<?php

namespace Drupal\service_manual_workflow;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;

/**
 * Service notification trait.
 */
trait ServiceNotificationTrait {

  /**
   * Get service owner method.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   Node object.
   *
   * @return array
   *   Array of municipality updatee users.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getServiceOwner(NodeInterface $entity) : array {
    $user = [];
    if ($entity->field_responsible_updatee->isEmpty()) {
      return $user;
    }
    $responsible_user = $entity->field_responsible_updatee->entity;
    if ($responsible_user->isBlocked()) {
      return $user;
    }
    $user[] = $responsible_user;
    return $user;
  }

  /**
   * Group getter from content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity object.
   *
   * @return array|bool|\Drupal\group\Entity\GroupInterface|mixed
   *   Group from entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionExceptio
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
   *   Content entity object.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group object.
   *
   * @return array
   *   Array of group admin users.
   */
  protected function getEntityGroupAdministration(ContentEntityInterface $entity, GroupInterface $group) : array {
    $accounts = [];
    $grid = $group->getGroupType()->id() . '-administrator';

    foreach ($group->getMembers() as $member) {
      $relationship = $member->getGroupRelationship();
      $roles = $relationship->getRoles();
      // If user has no roles in group or it isn't administrator role
      // continue for next loop.
      if (empty($roles) || empty($roles[$grid])) {
        continue;
      }
      // Validate user has publish access for current node.
      $account = $relationship->getEntity();
      $allowed = $this->stateTransitionValidation->allowedTransitions($account, $entity, [$group]);
      if (empty($allowed['publish'])) {
        continue;
      }
      $accounts[$account->getLastAccessedTime()] = $account;
    }

    ksort($accounts);

    return $accounts;
  }

  /**
   * Get the group from the current route match.
   *
   * @return bool|\Drupal\group\Entity\GroupInterface
   *   Group object.
   *
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
