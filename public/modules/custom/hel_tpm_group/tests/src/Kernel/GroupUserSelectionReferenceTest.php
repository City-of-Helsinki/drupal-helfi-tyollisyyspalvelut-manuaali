<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\PermissionScopeInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

/**
 * Tests field reference for group user selection.
 *
 * @coversDefaultClass \Drupal\hel_tpm_group\Plugin\EntityReferenceSelection\GroupUserSelection
 *
 * @group hel_tpm_group
 */
class GroupUserSelectionReferenceTest extends GroupKernelTestBase {

  use EntityReferenceFieldCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'hel_tpm_group',
    'node',
    'group',
    'group_test_config',
    'group_test_plugin',
    'ggroup',
    'message',
    'message_notify',
    'content_moderation',
    'gcontent_moderation',
    'workflows',
    'entitygroupfield',
  ];

  /**
   * User for testing.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $user1;

  /**
   * User for testing.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $user2;

  /**
   * User for testing.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $user3;

  /**
   * User for testing.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $user4;

  /**
   * Entity for testing.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $entity;

  /**
   * Entity for testing.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $subgroupEntity;

  /**
   * Group for testing.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected GroupInterface $group;

  /**
   * Group for testing.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected GroupInterface $subgroup;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->installSchema('ggroup', ['group_graph']);

    $module_configs = [
      'content_moderation',
      'gcontent_moderation',
      'group',
      'group_test_config',
      'group_test_plugin',
      'field',
      'node',
      'workflows',
      'entitygroupfield',
    ];
    $this->installConfig($module_configs);

    $groupType = $this->createGroupType(['id' => 'foo', 'creator_membership' => FALSE]);
    $storage = $this->entityTypeManager->getStorage('group_content_type');
    $storage->save($storage->createFromPlugin($groupType, 'subgroup:foo'));
    $storage->save($storage->createFromPlugin($groupType, 'node_as_content:article'));
    $storage->save($storage->createFromPlugin($groupType, 'user_as_content'));

    $group_role = $this->createGroupRole([
      'group_type' => $groupType->id(),
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'id' => 'foo-editor',
    ]);

    $this->group = Group::create([
      'id' => 10,
      'type' => $groupType->id(),
      'label' => 'Test group',
    ]);
    $this->group->save();

    $this->subgroup = Group::create([
      'id' => 11,
      'type' => $groupType->id(),
      'label' => 'Test subgroup',
    ]);
    $this->subgroup->save();
    $this->group->addRelationship($this->subgroup, 'subgroup:foo');

    $node_type = NodeType::create([
      'type' => 'article',
    ]);
    $node_type->save();

    $this->entity = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]);
    $this->entity->save();
    $this->group->addRelationship($this->entity, 'node_as_content:article');

    $this->subgroupEntity = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]);
    $this->subgroupEntity->save();
    $this->subgroup->addRelationship($this->subgroupEntity, 'node_as_content:article');

    $this->createEntityReferenceField(
      'node',
      $node_type->id(),
      'test_group_user',
      'Group user selection',
      'user',
      'hel_tpm_group_editor_user_selection');

    $this->user1 = $this->createUser([], 'TestA');
    $this->user1->save();
    $this->group->addMember($this->user1, ['group_roles' => [$group_role->id()]]);

    $this->user2 = $this->createUser([], 'TestB');
    $this->user2->save();
    $this->subgroup->addMember($this->user2, ['group_roles' => [$group_role->id()]]);

    $this->user3 = $this->createUser([], 'TestC');
    $this->user3->save();

    $this->user4 = $this->createUser([], 'TestD');
    $this->user4->save();
    $this->subgroup->addMember($this->user4);
  }

  /**
   * Tests field in group node has group users.
   *
   * @return void
   *   -
   */
  public function testHasGroupUsers(): void {
    /** @var \Drupal\Core\Entity\EntityAutocompleteMatcherInterface $autocomplete */
    $autocomplete = \Drupal::service('entity.autocomplete_matcher');

    $matches = $autocomplete->getMatches(
      'user',
      'hel_tpm_group_editor_user_selection',
      ['entity' => $this->entity],
      'Test');
    $this->assertCount(1, $matches);

    $users = $this->getLabelsFromMatches($matches);
    $this->assertContains($this->user1->getEmail(), $users);
    $this->assertNotContains($this->user2->getEmail(), $users);
    $this->assertNotContains($this->user3->getEmail(), $users);
    $this->assertNotContains($this->user4->getEmail(), $users);
  }

  /**
   * Tests field in subgroup node only has subgroup users.
   *
   * @return void
   *   -
   */
  public function testOnlySubgroupUsers(): void {
    /** @var \Drupal\Core\Entity\EntityAutocompleteMatcherInterface $autocomplete */
    $autocomplete = \Drupal::service('entity.autocomplete_matcher');

    $matches = $autocomplete->getMatches(
      'user',
      'hel_tpm_group_editor_user_selection',
      [
        'entity' => $this->subgroupEntity,
        'include_supergroup_members' => FALSE,
      ],
      'Test');
    $this->assertCount(1, $matches);

    $users = $this->getLabelsFromMatches($matches);
    $this->assertNotContains($this->user1->getEmail(), $users);
    $this->assertContains($this->user2->getEmail(), $users);
    $this->assertNotContains($this->user3->getEmail(), $users);
    $this->assertNotContains($this->user4->getEmail(), $users);
  }

  /**
   * Tests field in subgroup node has also users from parent group.
   *
   * @return void
   *   -
   *
   * @group exclude
   */
  public function testHasParentGroupUsers(): void {
    /** @var \Drupal\Core\Entity\EntityAutocompleteMatcherInterface $autocomplete */
    $autocomplete = \Drupal::service('entity.autocomplete_matcher');

    $matches = $autocomplete->getMatches(
      'user',
      'hel_tpm_group_editor_user_selection',
      [
        'entity' => $this->subgroupEntity,
        'include_supergroup_members' => TRUE,
      ],
      'Test');

    $this->assertCount(2, $matches);

    $users = $this->getLabelsFromMatches($matches);
    $this->assertContains($this->user1->getEmail(), $users);
    $this->assertContains($this->user2->getEmail(), $users);
    $this->assertNotContains($this->user3->getEmail(), $users);
    $this->assertNotContains($this->user4->getEmail(), $users);
  }

  /**
   * Gets labels from matches.
   *
   * @param array $matches
   *   The array where items contain label keys.
   *
   * @return array
   *   Labels in array.
   */
  protected function getLabelsFromMatches(array $matches): array {
    $labels = [];
    foreach ($matches as $match) {
      $labels[] = $match['label'];
    }
    return $labels;
  }

}
