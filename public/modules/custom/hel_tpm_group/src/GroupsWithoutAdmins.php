<?php declare(strict_types = 1);

namespace Drupal\hel_tpm_group;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * @todo Add class description.
 */
final class GroupsWithoutAdmins {

  private static $roles = [
    'service_provider-group_admin',
    'organisation-administration'
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
  public function groupsWithoutAdmins(): void {
    $groups = $this->entityTypeManager->getStorage('group')->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    $this->database->select('')
    foreach($groups as $group) {

    }
  }

}
