<?php

declare(strict_types = 1);

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\PermissionScopeInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

/**
 * Tests field reference for general group selection.
 *
 * @coversDefaultClass \Drupal\hel_tpm_group\Plugin\EntityReferenceSelection\GroupSelection
 *
 * @group hel_tpm_group
 */
class GroupSelectionReferenceTest extends GroupKernelTestBase {

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
  protected EntityInterface $node;

  /**
   * Group for testing.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected GroupInterface $group;

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
    $storage->save($storage->createFromPlugin($groupType, 'node_as_content:article'));

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
    $this->group->addMember($this->user, ['group_roles' => ['foo-editor']]);

    $nodeType = NodeType::create([
      'type' => 'article',
    ]);
    $nodeType->save();
    $this->node = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]);
    $this->node->save();

    $this->createEntityReferenceField(
      'node',
      $nodeType->id(),
      'test_group_selection',
      'General group selection',
      'group',
      'hel_tpm_group_group_selection',
    );
  }

  /**
   * Tests showing published group.
   *
   * @return void
   *   -
   */
  public function testPublished(): void {
    /** @var \Drupal\Core\Entity\EntityAutocompleteMatcherInterface $autocomplete */
    $autocomplete = \Drupal::service('entity.autocomplete_matcher');
    $matches = $autocomplete->getMatches(
      'group',
      'hel_tpm_group_group_selection',
      [
        'entity' => $this->node,
        'published_filter' => 'published',
      ],
      'Test');
    $this->assertCount(1, $matches);
  }

  /**
   * Tests not showing published group.
   *
   * @return void
   *   -
   */
  public function testUnpublished(): void {
    /** @var \Drupal\Core\Entity\EntityAutocompleteMatcherInterface $autocomplete */
    $autocomplete = \Drupal::service('entity.autocomplete_matcher');
    $matches = $autocomplete->getMatches(
      'group',
      'hel_tpm_group_group_selection',
      [
        'entity' => $this->node,
        'published_filter' => 'unpublished',
      ],
      'Test');
    $this->assertCount(0, $matches);
  }

}
