<?php declare(strict_types = 1);

namespace Drupal\hel_tpm_group;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupMembership;

/**
 * @todo Add class description.
 */
final class GroupsWithoutAdmins {

  private static $roles = [
    'service_provider-group_admin',
    'organisation-administrator'
  ];

  /**
   * Constructs a GroupsWithoutAdmins object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database
  ) {}

  /**
   * @todo Add method description.
   */
  public function groupsWithoutAdmins(): array {
    $groups = $this->entityTypeManager->getStorage('group')->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    foreach($groups as $gid => $group) {
      if (!$this->groupHasAdminUsers($group, self::$roles)) {
        continue;
      }
      unset($groups[$gid]);
    }
    return $groups;
  }

  /**
   * Group has members with roles method.
   *
   * @param $gid
   *
   * @return array|\Drupal\Core\Entity\EntityInterface[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function groupHasAdminUsers($gid, $roles, $count = FALSE) {
    $storage = \Drupal::entityTypeManager()->getStorage('group_content');

    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('gid', $gid)
      ->condition('plugin_id', 'group_membership');

    if (isset($roles)) {
      $query->condition('group_roles', (array) $roles, 'IN');
    }

    $query->count();
    $count = $query->execute();
    return $count <= 0 ? FALSE : TRUE;
  }
}
