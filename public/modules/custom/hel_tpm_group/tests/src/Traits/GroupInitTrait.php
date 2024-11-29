<?php
namespace Drupal\Tests\hel_tpm_group\Traits;

use Drupal\group\Entity\GroupInterface;

trait GroupInitTrait {

  protected $spUser;
  protected $spGroup;

  protected $orgGroup;

  protected $orgUser;

  protected $orgUser2;

  /**
   * Initialize groups, roles and users.
   *
   * @return void
   *   -
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function initGroups($create_subgroup = FALSE) {
    // Create organisation group.
    $this->orgGroup = $this->createGroup(['type' => 'organisation']);

    // Create user for organisation group and add it to group.
    $this->orgUser = $this->createUserWithRoles(['specialist editor', 'editor']);
    $this->orgGroup->addMember($this->orgUser, ['group_roles' => ['organisation-administrator']]);

    // Create service provider specialist editor.
    $this->orgUser2 = $this->createUserWithRoles(['specialist editor', 'editor']);
    $this->orgGroup->addMember($this->orgUser, ['group_roles' => ['organisation-editor']]);


    if ($create_subgroup === TRUE) {
      // Create service provider group.
      $this->spUser = $this->createUserWithRoles(['editor']);
      $this->spGroup = $this->createGroup(['type' => 'service_provider']);
      $this->spGroup->addMember($this->spUser, ['group_roles' => 'service_provider-group_admin']);

      // Add service provider to organisation group as subgroup.
      $this->orgGroup->addRelationship($this->spGroup, 'subgroup:service_provider');
    }
  }

  protected function createGroupContent(GroupInterface $group) {
    $content_plugin = 'group_node:service';
    $node = $this->createNode([
      'type' => 'service',
      'uid' => $this->orgUser->id(),
      'moderation_state' => 'draft',
    ]);
    $node->save();
    // Add created node to group.
    $group->addRelationship($node, $content_plugin);
    $node->set('moderation_state', 'published');
    $node->save();

    return $node;
  }

}
