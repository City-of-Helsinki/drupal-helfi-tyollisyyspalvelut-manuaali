<?php

namespace Drupal\hel_tpm_group;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationship;

/**
 * Hel_tpm_group.current_group service class.
 */
class CurrentGroup {

  /**
   * Route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  private $routeMatch;

  /**
   * Constructor for CurrentGroup service class.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match service.
   */
  public function __construct(RouteMatchInterface $routeMatch) {
    $this->routeMatch = $routeMatch;
  }

  /**
   * Get group by given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Content entity object.
   *
   * @return bool|\Drupal\group\Entity\GroupInterface
   *   Returns groups if found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  private function getGroupByEntity(EntityInterface $entity) {
    $group = FALSE;
    if ($entity instanceof GroupInterface) {
      return $entity;
    }
    // Load all the group content for this entity.
    /** @var \Drupal\group\Entity\GroupRelationship $group_content */
    $group_content = GroupRelationship::loadByEntity($entity);
    // Assuming that the content can be related only to 1 group.
    $group_content = reset($group_content);
    if (!empty($group_content)) {
      $group = $group_content->getGroup();
    }
    return $group;
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
    $entity = FALSE;
    $parameters = $this->routeMatch->getParameters()->all();
    if (!empty($parameters['group']) && is_numeric($parameters['group'])) {
      $group = Group::load($parameters['group']);
      return $group;
    }
    if (!empty($parameters)) {
      foreach ($parameters as $parameter) {
        if ($parameter instanceof EntityInterface) {
          $entity = $parameter;
          break;
        }
      }
    }
    if ($entity) {
      return $this->getGroupByEntity($entity);
    }
    return FALSE;
  }

}
