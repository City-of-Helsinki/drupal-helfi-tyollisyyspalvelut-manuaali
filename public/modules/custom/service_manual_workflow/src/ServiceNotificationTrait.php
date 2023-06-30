<?php
namespace Drupal\service_manual_workflow;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;

trait ServiceNotificationTrait {

  /**
   * @param \Drupal\node\NodeInterface $entity
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getServiceOwner(NodeInterface $entity) : array {
    $user = [];
    if ($entity->field_responsible_updatee->isEmpty()) {
      return $user;
    }
    $user[] = $entity->field_responsible_updatee->entity;
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