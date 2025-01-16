<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\views\Views;

/**
 * Tests for moderation_state_filter_exclude_archived views filter.
 *
 * @coversDefaultClass \Drupal\hel_tpm_group\Plugin\views\filter\ModerationStateExcludeArchived
 *
 * @group hel_tpm_group
 */
class ViewsModerationStateExcludeArchivedTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hel_tpm_group',
    'hel_tpm_group_test_views',
    'text',
    'node',
    'system',
    'user',
    'workflows',
    'field',
    'content_moderation',
    'group',
    'gcontent_moderation',
    'message',
    'message_notify',
    'message_notify_test',
    'gnode',
    'ggroup',
    'ggroup_role_mapper',
    'field_permissions',
    'flexible_permissions',
    'service_manual_workflow',
    'variationcache',
    'service_manual_workflow_service_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_content');
    $this->installEntitySchema('group_config_wrapper');
    $this->installConfig(['group']);

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'node', 'system']);
    $this->installSchema('ggroup', ['group_graph']);
    $this->installConfig([
      'service_manual_workflow_service_test',
      'content_moderation',
    ]);

    $this->installConfig('hel_tpm_group_test_views');
  }

  /**
   * Tests views result when applying filter options.
   */
  public function testViewResults() {
    $node_published = Node::create([
      'type' => 'service',
      'title' => 'Test 1',
      'moderation_state' => 'draft',
    ]);
    $node_published->save();
    $node_published->setNewRevision();
    $node_published->moderation_state = 'published';
    $node_published->save();

    $node_draft = Node::create([
      'type' => 'service',
      'title' => 'Test 2',
      'moderation_state' => 'draft',
    ]);
    $node_draft->save();

    $node_ready_to_publish = Node::create([
      'type' => 'service',
      'title' => 'Test 3',
      'moderation_state' => 'ready_to_publish',
    ]);
    $node_ready_to_publish->save();

    $node_outdated = Node::create([
      'type' => 'service',
      'title' => 'Test 4',
      'moderation_state' => 'outdated',
    ]);
    $node_outdated->save();

    $node_archived = Node::create([
      'type' => 'service',
      'title' => 'Test 5',
      'moderation_state' => 'archived',
    ]);
    $node_archived->save();

    // Ensure archived services are not listed with the default option.
    $this->assertWithFilters([
      $node_published,
      $node_draft,
      $node_ready_to_publish,
      $node_outdated,
    ], []);

    // Ensure archived services are listed with the archived option.
    $this->assertWithFilters([
      $node_archived,
    ], [
      'moderation_state_filter_exclude_archived' => 'service_moderation-archived',
    ]);

  }

  /**
   * Ensure nodes are included in the view result with the given filters.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   Nodes that should be included in the views result.
   * @param array $filters
   *   Filters and their values.
   */
  protected function assertWithFilters(array $nodes, array $filters): void {
    $view = Views::getView('test_moderation_state_excluding_archived');
    $view->setExposedInput($filters);
    $view->execute();

    $expected = [];
    foreach ($nodes as $node) {
      $expected[] = ['nid' => $node->id()];
    }
    $this->assertIdenticalResultset($view, $expected, ['nid' => 'nid']);
  }

}
