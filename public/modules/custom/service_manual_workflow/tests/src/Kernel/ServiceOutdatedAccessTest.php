<?php

namespace Drupal\Tests\service_manual_workflow\Kernel;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\group\PermissionScopeInterface;
use Drupal\user\RoleInterface;
use Drupal\workflows\Entity\Workflow;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Service outdated access tests.
 *
 * @group service_manual_workflow.
 *
 * @covers \Drupal\service_manual_workflow\Access\ServiceOutdatedAccess
 */
class ServiceOutdatedAccessTest extends GroupKernelTestBase {

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
    'service_manual_workflow',
    'group_test_config',
    'ggroup',
    'group',
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
    $this->installConfig('service_manual_workflow');

    $this->createEditorialWorkflow();

    // Enable workflow.
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->entityTypeManager->getStorage('workflow')->load('editorial');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'article');
    $workflow->save();

    // Setup the group type.
    $member_permissions = [
      'use editorial transition publish',
      'use editorial transition outdated',
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
    $this->groupMember = $this->createUser(['use editorial transition create_new_draft']);
    $this->group->addRelationship($this->groupNode, 'group_node:article');
    $this->group->addMember($this->groupMember);
  }

  /**
   * Service outdated access tests.
   */
  public function testServiceOutdatedAccess() {
    $outdated_access_service = \Drupal::service('service_manual_workflow.set_outdated_access');
    $access = $outdated_access_service->access($this->groupNode, $this->groupMember);
    $this->assertInstanceOf(AccessResultAllowed::class, $access);

    // Remove outdated transition permission.
    $this->groupRole->revokePermission('use editorial transition outdated');
    $this->groupRole->save();

    // Validate user cannot access outdated service.
    $access = $outdated_access_service->access($this->groupNode, $this->groupMember);
    $this->assertInstanceOf(AccessResultForbidden::class, $access);
  }

  /**
   * Create workflow with outdated state and transition.
   *
   * @return \Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|\Drupal\workflows\Entity\Workflow
   *   Workflow entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createEditorialWorkflow() {
    $workflow = Workflow::create([
      'type' => 'content_moderation',
      'id' => 'editorial',
      'label' => 'Editorial',
      'type_settings' => [
        'states' => [
          'archived' => [
            'label' => 'Archived',
            'weight' => 5,
            'published' => FALSE,
            'default_revision' => TRUE,
          ],
          'draft' => [
            'label' => 'Draft',
            'published' => FALSE,
            'default_revision' => FALSE,
            'weight' => -5,
          ],
          'published' => [
            'label' => 'Published',
            'published' => TRUE,
            'default_revision' => TRUE,
            'weight' => 0,
          ],
          'outdated' => [
            'label' => 'Outdated',
            'published' => FALSE,
            'default_revision' => TRUE,
            'weight' => 10,
          ],
        ],
        'transitions' => [
          'archive' => [
            'label' => 'Archive',
            'from' => ['published'],
            'to' => 'archived',
            'weight' => 2,
          ],
          'archived_draft' => [
            'label' => 'Restore to Draft',
            'from' => ['archived'],
            'to' => 'draft',
            'weight' => 3,
          ],
          'archived_published' => [
            'label' => 'Restore',
            'from' => ['archived'],
            'to' => 'published',
            'weight' => 4,
          ],
          'create_new_draft' => [
            'label' => 'Create New Draft',
            'to' => 'draft',
            'weight' => 0,
            'from' => [
              'draft',
              'published',
            ],
          ],
          'outdated' => [
            'label' => 'Outdated',
            'to' => 'outdated',
            'weight' => 6,
            'from' => [
              'outdated',
              'published',
              'draft',
            ],
          ],
          'publish' => [
            'label' => 'Publish',
            'to' => 'published',
            'weight' => 1,
            'from' => [
              'draft',
              'published',
            ],
          ],
        ],
      ],
    ]);
    $workflow->save();
    return $workflow;
  }

}
