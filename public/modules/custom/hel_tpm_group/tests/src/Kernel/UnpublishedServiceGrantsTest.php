<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\hel_tpm_group\Traits\GroupInitTrait;
use Drupal\Tests\service_manual_workflow\Traits\ServiceManualWorkflowTestTrait;
use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the group grants of unpublished services.
 *
 * @see hel_tpm_group_node_access_records()
 */
#[Group('hel_tpm_group')]
#[RunTestsInSeparateProcesses]
class UnpublishedServiceGrantsTest extends GroupKernelTestBase {

  use ContentModerationTestTrait;
  use GroupInitTrait;
  use ServiceManualWorkflowTestTrait;

  /**
   * The grant realm for unpublished services.
   */
  protected const REALM = 'hel_tpm_group_unpublished_realm';

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

    // The service provider group is a subgroup of the organisation group.
    $this->initGroups(TRUE);
  }

  /**
   * Tests one grant per group when a group is also a supergroup.
   */
  public function testGrantsAreUniqueForGroupAndSupergroup(): void {
    $node = $this->createServiceInGroups([$this->orgGroup, $this->spGroup]);
    $this->assertFalse($node->isPublished());

    // The organisation group is both a group of the service and the supergroup
    // of the service provider group.
    $grant_ids = array_column(hel_tpm_group_node_access_records($node), 'gid');
    sort($grant_ids);
    $this->assertSame($this->getExpectedGroupIds(), array_map('intval', $grant_ids));
  }

  /**
   * Tests saving an unpublished service in a group and its subgroup.
   */
  public function testSaveServiceInGroupAndSubgroup(): void {
    $node = $this->createServiceInGroups([$this->orgGroup, $this->spGroup]);

    // Writing duplicate grants failed with an integrity constraint violation.
    $node->setTitle($this->randomMachineName());
    $node->save();

    $grant_ids = $this->container->get('database')->select('node_access', 'na')
      ->fields('na', ['gid'])
      ->condition('nid', $node->id())
      ->condition('realm', self::REALM)
      ->orderBy('gid')
      ->execute()
      ->fetchCol();
    $this->assertSame($this->getExpectedGroupIds(), array_map('intval', $grant_ids));
  }

  /**
   * Creates an unpublished service in the given groups.
   *
   * The service is reloaded after adding the relationships. Saving the same
   * node object again would delete them, because its group field was computed
   * empty by the node access hook before the relationships existed.
   *
   * @param \Drupal\group\Entity\GroupInterface[] $groups
   *   The groups.
   *
   * @return \Drupal\node\NodeInterface
   *   The service.
   */
  protected function createServiceInGroups(array $groups): NodeInterface {
    $node = $this->createNode([
      'type' => 'service',
      'uid' => $this->orgUser->id(),
      'moderation_state' => 'draft',
    ]);
    foreach ($groups as $group) {
      $group->addRelationship($node, 'group_node:service');
    }
    $node = $this->reloadEntity($node);

    $group_ids = array_map(
      static fn (GroupInterface $group): int => (int) $group->id(),
      $groups,
    );
    sort($group_ids);
    $this->assertSame($group_ids, $this->getServiceGroupIds($node), 'The service belongs to all groups.');

    return $node;
  }

  /**
   * Gets the group IDs expected in the grants.
   *
   * @return int[]
   *   The organisation and service provider group IDs, sorted.
   */
  protected function getExpectedGroupIds(): array {
    $group_ids = [(int) $this->orgGroup->id(), (int) $this->spGroup->id()];
    sort($group_ids);
    return $group_ids;
  }

  /**
   * Gets the IDs of the groups the service belongs to.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The service.
   *
   * @return int[]
   *   Group IDs, sorted.
   */
  protected function getServiceGroupIds(NodeInterface $node): array {
    $storage = $this->entityTypeManager->getStorage('group_content');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_id', $node->id())
      ->condition('plugin_id', 'group_node:service')
      ->execute();

    $group_ids = [];
    /** @var \Drupal\group\Entity\GroupRelationshipInterface $relationship */
    foreach ($storage->loadMultiple($ids) as $relationship) {
      $group_ids[] = (int) $relationship->getGroupId();
    }
    sort($group_ids);
    return $group_ids;
  }

}
