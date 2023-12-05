<?php

namespace Drupal\Tests\service_manual_workflow\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use Drupal\workflows\Entity\Workflow;

/**
 * Tests the views 'moderation_state_filter' filter plugin.
 *
 * @coversDefaultClass \Drupal\service_manual_workflow\Plugin\views\filter\LatestModerationStateFilter
 *
 * @group content_moderation
 */
class ViewsLatestModerationStateFilterTest extends ViewsKernelTestBase {

  use ContentModerationTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'service_manual_workflow_test_views',
    'node',
    'content_moderation',
    'workflows',
    'workflow_type_test',
    'entity_test',
    'language',
    'content_translation',
    'service_manual_workflow',
    'group',
    'gcontent_moderation',
    'message_notify',
    'variationcache',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('entity_test_no_bundle');
    $this->installSchema('node', 'node_access');
    $this->installConfig('content_moderation');
    $this->installConfig('service_manual_workflow');

    $node_type = NodeType::create([
      'type' => 'example',
    ]);
    $node_type->save();

    $node_type = NodeType::create([
      'type' => 'another_example',
    ]);
    $node_type->save();

    $node_type = NodeType::create([
      'type' => 'example_non_moderated',
    ]);
    $node_type->save();

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'example');
    $workflow->save();

    // Install the test views after moderation has been enabled on the example
    // bundle, so the moderation_state field exists.
    $this->installConfig('service_manual_workflow_test_views');

    ConfigurableLanguage::createFromLangcode('fr')->save();
  }

  /**
   * Test that moderation state filter returns expected values.
   */
  public function testLatestModerationStateViewsFilter() {
    $workflow = Workflow::load('editorial');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'example');
    $workflow->getTypePlugin()->addState('translated_draft', 'Bar');
    $configuration = $workflow->getTypePlugin()->getConfiguration();
    $configuration['states']['translated_draft'] += [
      'published' => FALSE,
      'default_revision' => FALSE,
    ];
    $workflow->getTypePlugin()->setConfiguration($configuration);
    $workflow->save();

    // Create a published default revision and one forward draft revision.
    $node = Node::create([
      'type' => 'example',
      'title' => 'Test Node',
      'status' => TRUE,
      'moderation_state' => 'published',
    ]);

    $node->save();
    $node->setNewRevision();
    $node->set('status', FALSE);
    $node->set('revision_default', FALSE);
    $node->moderation_state = 'draft';
    $node->save();

    // Create a draft default revision.
    $second_node = Node::create([
      'type' => 'example',
      'title' => 'Second Node',
      'moderation_state' => 'published',
      'status' => TRUE,
    ]);
    $second_node->save();

    // Test the filter within an AND filter group (the default) and an OR filter
    // group.
    $base_table_views = [
      'test_latest_content_moderation_state_filter_base_table',
    ];
    foreach ($base_table_views as $view_id) {
      // The two default revisions are listed when no filter is specified.
      $this->assertNodesWithFilters([$node, $second_node], [], $view_id);

      // The default revision of node one and three are published.
      $this->assertNodesWithFilters([$node], [
        'moderation_state' => 'editorial-draft',
      ], $view_id);

      // The default revision of node two is draft.
      $this->assertNodesWithFilters([$second_node], [
        'moderation_state' => 'editorial-published',
      ], $view_id);
    }
  }

  /**
   * Assert the nodes appear when the test view is executed.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   Nodes to assert are in the views result.
   * @param array $filters
   *   An array of filters to apply to the view.
   * @param string $view_id
   *   The view to execute for the results.
   *
   * @internal
   */
  protected function assertNodesWithFilters(array $nodes, array $filters, string $view_id = 'test_content_moderation_state_filter_base_table'): void {
    $view = Views::getView($view_id);
    $view->setExposedInput($filters);
    $view->execute();

    $query = $view->getQuery();
    $join = $query->getTableInfo('content_moderation_state');
    // Verify the join configuration.
    if (!empty($join)) {
      $join = $query->getTableInfo('content_moderation_state')['join'];
      $configuration = $join->configuration;

      $this->assertEquals('nid', $configuration['left_field']);
      $this->assertEquals('content_entity_id', $configuration['field']);
      $this->assertEquals('content_entity_type_id', $configuration['extra'][0]['field']);
      $this->assertEquals('node', $configuration['extra'][0]['value']);

      $this->assertEquals('content_entity_id', $configuration['extra'][1]['field']);
      $this->assertEquals('nid', $configuration['extra'][1]['left_field']);
      $this->assertEquals('langcode', $configuration['extra'][2]['field']);
      $this->assertEquals('langcode', $configuration['extra'][2]['left_field']);
    }
    $expected_result = [];
    foreach ($nodes as $node) {
      $expected_result[] = ['nid' => $node->id()];
    }

    $this->assertIdenticalResultset($view, $expected_result, ['nid' => 'nid']);
  }

}
