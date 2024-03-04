<?php declare(strict_types = 1);

namespace Drupal\hel_tpm_group;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * @todo Add class description.
 */
final class GroupsWithoutAdmins {

  /**
   * Constructs a GroupsWithoutAdmins object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * @todo Add method description.
   */
  public function groupsWithoutAdmins(): void {
    $this->entityTypeManager->getStorage('group')
  }

}
