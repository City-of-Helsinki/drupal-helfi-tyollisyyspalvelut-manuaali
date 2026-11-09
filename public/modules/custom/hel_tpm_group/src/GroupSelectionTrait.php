<?php

namespace Drupal\hel_tpm_group;

use Drupal\Component\Utility\Html;
use Drupal\node\NodeInterface;

/**
 * Trait used in group selection.
 */
trait GroupSelectionTrait {

  /**
   * Route matcher service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Get groups from node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node object.
   * @param bool $include_supergroups
   *   Boolean to determine if super groups should be returned.
   * @param bool $load_groups
   *   Boolean to determine if groups should be loaded or not.
   *
   * @return array
   *   Array of groups.
   */
  protected function getGroups(NodeInterface $node, bool $include_supergroups, bool $load_groups = FALSE): array {
    $groups = [];
    if (!$node instanceof NodeInterface) {
      return $groups;
    }

    $route_group = $node->isNew() ? $this->routeMatch?->getParameter('group') : NULL;
    if (!empty($route_group)) {
      $groups[] = $load_groups ? $route_group : $route_group->id();
    }
    // Saved services, and new services created outside a group, get their
    // groups from the group field, which the service form keeps up to date.
    elseif (!empty($node->entitygroupfield)) {
      foreach ($node->entitygroupfield->referencedEntities() as $group) {
        if (empty($group)) {
          continue;
        }
        if ($load_groups === FALSE) {
          $groups[$group->getGroup()->id()] = $group->getGroup()->id();
        }
        else {
          $groups[$group->getGroup()->id()] = $group->getGroup();
        }
      }
    }

    // Return if no groups found.
    if (empty($groups)) {
      return [];
    }

    if ($include_supergroups) {
      // Fetch parent groups for subgroups.
      foreach ($groups as $group) {
        // If groups are not loaded get only ids.
        if ($load_groups === FALSE) {
          $super_groups = $this->groupHierarchyManager->getGroupSupergroupIds($group);
        }
        else {
          // Child groups are loaded get supergroups using group id.
          $super_groups = $this->groupHierarchyManager->getGroupSupergroups($group->id());
        }
        if (empty($super_groups)) {
          continue;
        }
        $groups = array_merge($groups, $super_groups);
      }
    }

    return $groups;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $target_type = $this->getConfiguration()['target_type'];

    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $result = $query->execute();

    if (empty($result)) {
      return [];
    }

    $options = [];
    $entities = $this->entityTypeManager->getStorage($target_type)->loadMultiple($result);
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      $options[$bundle][$entity_id] = Html::escape($entity->label());
    }

    return $options;
  }

  /**
   * Helper method to create mock service.
   */
  private function mockService() {
    return $this->entityTypeManager->getStorage('node')->create([
      'type' => 'service',
      'name' => 'MockService',
    ]);
  }

}
