<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
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
class ParentGroupSelectionReferenceTest extends GroupKernelTestBase {

  use EntityReferenceTestTrait;

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
  protected User $user;

  /**
   * Entity for testing.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $groupEntity;

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
   * Group for testing.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected GroupInterface $otherGroup;

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

    $this->user = $this->createUser(['administer group'], $this->randomMachineName());
    $this->user->save();
    $this->setCurrentUser($this->user);

    $groupType = $this->createGroupType(['id' => 'foo', 'creator_membership' => TRUE]);
    $storage = $this->entityTypeManager->getStorage('group_content_type');
    $storage->save($storage->createFromPlugin($groupType, 'subgroup:foo'));
    $storage->save($storage->createFromPlugin($groupType, 'node_as_content:article'));
    $storage->save($storage->createFromPlugin($groupType, 'user_as_content'));

    // Create groups.
    $this->createGroupRole([
      'group_type' => $groupType->id(),
      'scope' => PermissionScopeInterface::INSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'permissions' => ['view group'],
      'id' => 'foo-editor',
    ]);
    $this->group = Group::create([
      'id' => 10,
      'type' => $groupType->id(),
      'label' => 'TestGroupA',
    ]);
    $this->group->save();
    $this->subgroup = Group::create([
      'id' => 11,
      'type' => $groupType->id(),
      'label' => 'TestGroupB',
    ]);
    $this->subgroup->save();
    $this->group->addRelationship($this->subgroup, 'subgroup:foo');
    $this->otherGroup = Group::create([
      'id' => 12,
      'type' => $groupType->id(),
      'label' => 'TestGroupC',
    ]);
    $this->otherGroup->save();

    // Add current user to groups.
    $this->group->addMember($this->user, ['group_roles' => ['foo-editor']]);
    $this->subgroup->addMember($this->user, ['group_roles' => ['foo-editor']]);

    // Create node entities for groups.
    $node_type = NodeType::create([
      'type' => 'article',
    ]);
    $node_type->save();
    $this->groupEntity = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]);
    $this->groupEntity->save();
    $this->group->addRelationship($this->groupEntity, 'node_as_content:article');
    $this->subgroupEntity = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]);
    $this->subgroupEntity->save();
    $this->subgroup->addRelationship($this->subgroupEntity, 'node_as_content:article');

    $this->createEntityReferenceField(
      'node',
      $node_type->id(),
      'test_parent_group',
      'Parent group selection',
      'group',
      'hel_tpm_group_parent_group_selection');
  }

  /**
   * Tests field in group node has groups.
   *
   * @return void
   *   -
   */
  public function testHasGroups(): void {
    /** @var \Drupal\Core\Entity\EntityAutocompleteMatcherInterface $autocomplete */
    $autocomplete = \Drupal::service('entity.autocomplete_matcher');

    $matches = $autocomplete->getMatches(
      'group',
      'hel_tpm_group_parent_group_selection',
      [
        'entity' => $this->groupEntity,
      ],
      'Test');
    $this->assertCount(1, $matches);

    $groups = $this->getLabelsFromMatches($matches);
    $this->assertContains($this->group->label(), $groups);
    $this->assertNotContains($this->subgroup->label(), $groups);
    $this->assertNotContains($this->otherGroup->label(), $groups);
  }

  /**
   * Tests field in subgroup node does not have parent group.
   *
   * @return void
   *   -
   */
  public function testSubgroupHasGroups(): void {
    /** @var \Drupal\Core\Entity\EntityAutocompleteMatcherInterface $autocomplete */
    $autocomplete = \Drupal::service('entity.autocomplete_matcher');

    $matches = $autocomplete->getMatches(
      'group',
      'hel_tpm_group_parent_group_selection',
      [
        'entity' => $this->subgroupEntity,
        'include_supergroup' => FALSE,
      ],
      'Test');
    $this->assertCount(1, $matches);

    $groups = $this->getLabelsFromMatches($matches);
    $this->assertNotContains($this->group->label(), $groups);
    $this->assertContains($this->subgroup->label(), $groups);
    $this->assertNotContains($this->otherGroup->label(), $groups);
  }

  /**
   * Tests field in subgroup node also has parent group.
   *
   * @return void
   *   -
   *
   * @group exclude
   */
  public function testSubgroupHasParentGroupAlso(): void {
    /** @var \Drupal\Core\Entity\EntityAutocompleteMatcherInterface $autocomplete */
    $autocomplete = \Drupal::service('entity.autocomplete_matcher');

    $matches = $autocomplete->getMatches(
      'group',
      'hel_tpm_group_parent_group_selection',
      [
        'entity' => $this->subgroupEntity,
        'include_supergroup' => TRUE,
      ],
      'Test');
    $this->assertCount(2, $matches);

    $groups = $this->getLabelsFromMatches($matches);
    $this->assertContains($this->group->label(), $groups);
    $this->assertContains($this->subgroup->label(), $groups);
    $this->assertNotContains($this->otherGroup->label(), $groups);
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
