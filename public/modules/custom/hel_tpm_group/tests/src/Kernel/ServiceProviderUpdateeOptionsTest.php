<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\hel_tpm_group\Traits\GroupInitTrait;
use Drupal\Tests\service_manual_workflow\Traits\ServiceManualWorkflowTestTrait;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\hel_tpm_group\Hook\ServiceProviderUpdateeHooks;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the service provider updatee options for changed service groups.
 */
#[CoversClass(ServiceProviderUpdateeHooks::class)]
#[Group('hel_tpm_group')]
#[RunTestsInSeparateProcesses]
class ServiceProviderUpdateeOptionsTest extends GroupKernelTestBase {

  use ContentModerationTestTrait;
  use GroupInitTrait;
  use ServiceManualWorkflowTestTrait;

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'node',
    'system',
    'user',
    'workflows',
    'hel_tpm_mail_tools',
    'hel_tpm_group',
    'field',
    'content_moderation',
    'content_translation',
    'language',
    'gcontent_moderation',
    'message',
    'message_notify',
    'message_notify_test',
    'gnode',
    'group',
    'ggroup',
    'ggroup_role_mapper',
    'field_permissions',
    'flexible_permissions',
    'entitygroupfield',
    'service_manual_workflow',
    'service_manual_workflow_service_test',
  ];

  /**
   * The hook class under test.
   *
   * @var \Drupal\hel_tpm_group\Hook\ServiceProviderUpdateeHooks
   */
  protected ServiceProviderUpdateeHooks $hooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('group');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('ggroup', ['group_graph']);

    $this->installConfig(['system', 'field', 'node']);
    $this->installConfig([
      'content_moderation',
      'gcontent_moderation',
      'group',
      'workflows',
      'hel_tpm_group',
      'service_manual_workflow_service_test',
    ]);

    $this->hooks = \Drupal::classResolver(ServiceProviderUpdateeHooks::class);
    $this->initGroups(TRUE);
  }

  /**
   * Tests that options follow a group change that isn't saved yet.
   */
  public function testOptionsFollowUnsavedGroupChange(): void {
    $node = $this->createService($this->orgGroup);

    $options = $this->hooks->getOptions($node, $this->loadRelationships($node));
    $this->assertArrayHasKey((int) $this->orgUser->id(), $options);
    $this->assertArrayNotHasKey((int) $this->spUser->id(), $options);

    // Move the service to the service provider group in the form.
    $options = $this->hooks->getOptions($node, [$this->createRelationship($this->spGroup)]);
    $this->assertArrayHasKey((int) $this->spUser->id(), $options);
    $this->assertArrayNotHasKey((int) $this->orgUser->id(), $options);

    // The service itself still has its saved groups.
    $this->assertSame([(int) $this->orgGroup->id()], $this->getGroupIds($node));
  }

  /**
   * Tests that a removed group's editors are no longer options.
   */
  public function testOptionsWithoutGroups(): void {
    $node = $this->createService($this->orgGroup);

    $options = $this->hooks->getOptions($node, []);
    $this->assertArrayNotHasKey((int) $this->orgUser->id(), $options);
    $this->assertArrayNotHasKey((int) $this->spUser->id(), $options);
  }

  /**
   * Tests options for a new service with a group selected in the form.
   */
  public function testOptionsForNewService(): void {
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'service',
      'title' => $this->randomMachineName(),
      'uid' => $this->orgUser->id(),
    ]);

    $options = $this->hooks->getOptions($node, [$this->createRelationship($this->orgGroup)]);
    $this->assertArrayHasKey((int) $this->orgUser->id(), $options);
    $this->assertArrayNotHasKey((int) $this->spUser->id(), $options);
  }

  /**
   * Creates a service in a group.
   *
   * Unlike GroupInitTrait::createGroupContent(), the service is reloaded after
   * adding the relationship. Saving the same node object again would delete
   * the relationship, because its group field was computed empty by the node
   * access hook before the relationship existed.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   *
   * @return \Drupal\node\NodeInterface
   *   The service.
   */
  protected function createService(GroupInterface $group): NodeInterface {
    $node = $this->createNode([
      'type' => 'service',
      'uid' => $this->orgUser->id(),
      'moderation_state' => 'draft',
    ]);
    $group->addRelationship($node, 'group_node:service');
    return $this->reloadEntity($node);
  }

  /**
   * Creates an unsaved service relationship to a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   *
   * @return \Drupal\group\Entity\GroupRelationshipInterface
   *   The unsaved relationship.
   */
  protected function createRelationship(GroupInterface $group): GroupRelationshipInterface {
    $type_id = $this->entityTypeManager->getStorage('group_content_type')
      ->getRelationshipTypeId($group->bundle(), 'group_node:service');
    return $this->entityTypeManager->getStorage('group_content')->create([
      'type' => $type_id,
      'gid' => $group->id(),
    ]);
  }

  /**
   * Loads the saved group relationships of a service.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The service.
   *
   * @return \Drupal\group\Entity\GroupRelationshipInterface[]
   *   The relationships.
   */
  protected function loadRelationships(NodeInterface $node): array {
    $storage = $this->entityTypeManager->getStorage('group_content');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_id', $node->id())
      ->condition('plugin_id', 'group_node:service')
      ->execute();
    return array_values($storage->loadMultiple($ids));
  }

  /**
   * Gets the IDs of the groups the service belongs to.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The service.
   *
   * @return int[]
   *   Group IDs.
   */
  protected function getGroupIds(NodeInterface $node): array {
    return array_map(
      static fn (GroupRelationshipInterface $relationship): int => (int) $relationship->getGroupId(),
      $this->loadRelationships($node),
    );
  }

}
