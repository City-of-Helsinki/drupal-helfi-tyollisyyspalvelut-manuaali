<?php

namespace Drupal\Tests\service_manual_workflow\Kernel;

use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\group\PermissionScopeInterface;
use Drupal\user\RoleInterface;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Service outdated access tests.
 *
 * @group service_manual_workflow.
 *
 * @covers Drupal\service_manual_workflow\ContentGroupService
 */
class ContentGroupServiceTest extends GroupKernelTestBase {

  use ProphecyTrait;
  use NodeCreationTrait;
  use ContentModerationTestTrait;
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'filter',
    'node',
    'system',
    'user',
    'workflows',
    'flexible_permissions',
    'gcontent_moderation',
    'gcontent_moderation_test',
    'message_notify',
    'gnode',
    'ggroup',
    'service_manual_workflow',
    'group_test_config',
  ];

  /**
   * A group.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $group;

  /**
   * A group member.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $groupMember;

  /**
   * A group node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $groupNode;

  /**
   * A group role.
   *
   * @var \Drupal\group\Entity\GroupRole
   */
  protected $groupRole;

  /**
   * Content group service.
   *
   * @var \Drupal\service_manual_workflow\ContentGroupService
   */
  private mixed $contentGroupService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('node');
    $this->installConfig(['content_moderation', 'filter', 'node', 'text']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['group', 'group_test_config']);
    $this->createContentType(['type' => 'article']);
    $this->installConfig([
      'service_manual_workflow',
      'ggroup',
    ]
    );

    // Setup the group type.
    $member_permissions = [
      'view latest version',
      'view unpublished group_node:article entity',
    ];

    /** @var \Drupal\group\Entity\GroupTypeInterface $type */
    $type = $this->entityTypeManager->getStorage('group_type')->load('default');
    $this->groupRole = $this->createGroupRole([
      'group_type' => $type->id(),
      'scope' => PermissionScopeInterface::INSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'permissions' => $member_permissions,
    ]);

    // Enable node content.
    /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('group_content_type');
    $storage->createFromPlugin($type, 'group_node:article')->save();

    $this->group = $this->createGroup(['type' => 'default']);
    $this->groupNode = $this->createNode(['type' => 'article']);

    // Add the global permission to create new drafts. This will verify that
    // the content moderation part of the service is still working.
    $this->groupMember = $this->createUser();
    $this->group->addRelationship($this->groupNode, 'group_node:article');
    $this->group->addMember($this->groupMember);
    $this->contentGroupService = \Drupal::service('service_manual_workflow.content_group_service');
  }

  /**
   * Service outdated access tests.
   */
  public function testContentGroupService() {
    $this->assertEquals(
      $this->group->id(),
      $this->contentGroupService->getGroupsWithEntity($this->groupNode)[0]->id()
    );
  }

}
