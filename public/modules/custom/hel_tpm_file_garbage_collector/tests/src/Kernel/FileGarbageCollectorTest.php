<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_file_garbage_collector\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\hel_tpm_file_garbage_collector\FileGarbageCollector;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\workflows\Entity\Workflow;

/**
 * Test description.
 *
 * @group hel_tpm_file_garbage_collector
 */
final class FileGarbageCollectorTest extends EntityKernelTestBase {

  use ContentModerationTestTrait;
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hel_tpm_file_garbage_collector',
    'system',
    'file',
    'node',
    'field',
    'user',
    'language',
    'workflows',
    'content_moderation',
    'content_translation',
  ];

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  private $adminUser;

  /**
   * Garbage collector services.
   *
   * @var \Drupal\hel_tpm_file_garbage_collector\FileGarbageCollector
   */
  private mixed $garbageCollector;

  /**
   * Queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactoryInterface
   */
  private $queue;

  /**
   * Content translation language.
   *
   * @var string
   */
  private $translationLangcode = 'fi';

  /**
   * Test file 1.
   *
   * @var \Drupal\file\FileInterface
   */
  private $file1;

  /**
   * Test file 2.
   *
   * @var \Drupal\file\FileInterface
   */
  private $file2;

  /**
   * Test file 3.
   *
   * @var \Drupal\file\FileInterface
   */
  private $file3;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['content_moderation', 'field', 'node', 'system']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);

    // Create new node type with revisions enabled.
    $article = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
      'new_revision' => TRUE,
    ]);
    $article->save();

    $this->createEditorialWorkflow();

    // Enable workflow.
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->entityTypeManager->getStorage('workflow')->load('editorial');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'article');
    $workflow->save();

    // Create file field storage config for node content type.
    FieldStorageConfig::create([
      'field_name' => 'file_test',
      'entity_type' => 'node',
      'type' => 'file',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    $this->directory = $this->getRandomGenerator()->name(8);

    // Create file field config for article node type.
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'file_test',
      'bundle' => 'article',
      'settings' => ['file_directory' => $this->directory],
    ])->save();
    file_put_contents('public://example.txt', $this->randomMachineName());
    $this->file = File::create([
      'uri' => 'public://example.txt',
    ]);
    $this->file->save();

    file_put_contents('public://example2.txt', $this->randomMachineName());
    $this->file2 = File::create([
      'uri' => 'public://example2.txt',
    ]);
    $this->file2->save();

    file_put_contents('public://example2.txt', $this->randomMachineName());
    $this->file3 = File::create([
      'uri' => 'public://example3.txt',
    ]);
    $this->file3->save();

    $this->adminUser = $this->createUser(['administer nodes', 'access content'], values: ['uid' => 2]);
    $this->garbageCollector = \Drupal::service('hel_tpm_file_garbage_collector.collector');
    $this->queue = \Drupal::service('queue')->get(FileGarbageCollector::$queue);

    ConfigurableLanguage::createFromLangcode($this->translationLangcode)->save();
  }

  /**
   * Tests the garbage collection of file references in entities.
   *
   * This method verifies that the file garbage collector correctly handles
   * the addition, removal, and updating of file references in node entities.
   * It uses various timestamps to simulate different time intervals and
   * ensures proper queue handling when file references are updated or removed.
   *
   * @return void
   *   Return nothing.
   */
  public function testFileGarbageCollectorCollection(): void {
    $datetime = new DrupalDateTime('-8 months');
    $node = Node::create([
      'title' => $this->randomMachineName(8),
      'type' => 'article',
      'status' => 1,
      'file_test' => [
        [
          'target_id' => $this->file->id(),
        ],
        [
          'target_id' => $this->file2->id(),
        ],
      ],
      'created' => $datetime->getTimestamp(),
      'changed' => $datetime->getTimestamp(),
      'revision_timestamp' => $datetime->getTimestamp(),
    ]);
    $node->save();
    $node = $this->reloadEntity($node);

    $this->garbageCollector->collect();
    $this->assertEquals(0, $this->queue->numberOfItems());

    $datetime->add(\DateInterval::createFromDateString('3 months'));
    $values = ['file_test' => ['target_id' => $this->file2->id()]];
    $this->setNodeValues($node, $values, $datetime->getTimestamp());

    $this->queue->deleteQueue();
    $this->garbageCollector->collect();
    $this->assertEquals(1, $this->queue->numberOfItems());

    $values = ['file_test' => []];
    $this->setNodeValues($node, $values, $datetime->getTimestamp());
    $node->set('file_test', []);
    $node->setNewRevision(TRUE);
    $node->save();

    // Empty queue before running collection.
    $this->queue->deleteQueue();
    $this->garbageCollector->collect();
    $this->assertEquals(1, $this->queue->numberOfItems());
  }

  /**
   * Tests the garbage collection of file references in moderated entities.
   *
   * This method ensures that the file garbage collector handles file references
   * properly in node entities with varying moderation states. It verifies that
   * file references are removed or retained correctly when moderation states
   * and file relationships are updated over time. It also checks proper queue
   * behavior during these changes.
   *
   * @return void
   *   Return nothing.
   */
  public function testFileGarbageCollectionModerated() {
    $datetime = new DrupalDateTime('-8 months');
    $node = Node::create([
      'title' => $this->randomMachineName(8),
      'type' => 'article',
      'moderation_state' => 'draft',
      'file_test' => [
        [
          'target_id' => $this->file->id(),
        ],
        [
          'target_id' => $this->file2->id(),
        ],
      ],
      'created' => $datetime->getTimestamp(),
      'changed' => $datetime->getTimestamp(),
      'revision_timestamp' => $datetime->getTimestamp(),
    ]);

    $node->save();
    $node = $this->reloadEntity($node);

    $this->garbageCollector->collect();
    $this->assertEquals(0, $this->queue->numberOfItems());

    $values = [
      'moderation_state' => 'published',
    ];
    $this->setNodeValues($node, $values, $datetime->getTimestamp() + 100);

    $this->queue->deleteQueue();
    $this->garbageCollector->collect();

    $this->assertEquals(0, $this->queue->numberOfItems());

    $datetime->add(\DateInterval::createFromDateString('3 months'));
    $values = [
      'moderation_state' => 'published',
      'file_test' => ['target_id' => $this->file2->id()],
    ];
    $this->setNodeValues($node, $values, $datetime->getTimestamp());
    $this->queue->deleteQueue();
    $this->garbageCollector->collect();
    $this->assertEquals(1, $this->queue->numberOfItems());

    $values = [
      'moderation_state' => 'draft',
      'file_test' => [],
    ];
    $datetime = new DrupalDateTime('now');

    $this->setNodeValues($node, $values, $datetime->getTimestamp());

    // Empty queue before running collection.
    $this->queue->deleteQueue();
    $this->garbageCollector->collect();
    $this->assertEquals(1, $this->queue->numberOfItems());
  }

  /**
   * Tests the garbage collection of file references with translated moderation.
   *
   * This method ensures that the file garbage collector handles
   * scenarios involving translated node entities and moderation states.
   * It verifies proper handling of file references in
   * various moderation states (draft, published) and ensures
   * queue consistency across translated content,
   * time intervals, and state transitions.
   *
   * @return void
   *   Return nothing.
   */
  protected function testFileGarbageCollectionTranslatedModeration() {
    $datetime = new DrupalDateTime('-12 months');
    $node = Node::create([
      'title' => $this->randomMachineName(8),
      'type' => 'article',
      'moderation_state' => 'draft',
      'file_test' => [
        [
          'target_id' => $this->file->id(),
        ],
        [
          'target_id' => $this->file2->id(),
        ],
      ],
      'created' => $datetime->getTimestamp(),
      'changed' => $datetime->getTimestamp(),
      'revision_timestamp' => $datetime->getTimestamp(),
    ]);
    $values = [
      'title' => $this->randomString(),
      'moderation_state' => 'draft',
      'file_test' => [
        'target_id' => $this->file3->id(),
      ],
      'created' => $datetime->getTimestamp(),
      'changed' => $datetime->getTimestamp(),
      'revision_timestamp' => $datetime->getTimestamp(),
    ];
    $translation = $node->addTranslation($this->translationLangcode, $values);
    $translation->save();

    $this->garbageCollector->collect();
    $this->assertEquals(0, $this->queue->numberOfItems());

    $datetime->add(\DateInterval::createFromDateString('4 months'));
    $values = ['moderation_state' => 'published'];
    $this->setNodeValues($node, $values, $datetime->getTimestamp());
    $values = [
      'moderation_state' => 'published',
      'file_test' => ['target_id' => $this->file->id()],
    ];
    $this->setNodeValues($values, $node, $datetime->getTimestamp());

    $this->queue->deleteQueue();
    $this->garbageCollector->collect();
    $this->assertEquals(1, $this->queue->numberOfItems());

    $datetime->add(\DateInterval::createFromDateString('1 months'));
    $values = [
      'moderation_state' => 'draft',
      'file_test' => [],
    ];
    $this->setNodeValues($translation, $values, $datetime->getTimestamp());

    $this->queue->deleteQueue();
    $this->garbageCollector->collect();
    $this->assertEquals(0, $this->garbageCollector->numberOfItems());

    $datetime = new DrupalDateTime('now');
    $this->setNodeValues($node, ['moderation_state' => 'published', 'file_test' => []], $datetime->getTimestamp());
    $this->setNodeValues($translation, [], $datetime->getTimestamp());

    $this->queue->deleteQueue();
    $this->garbageCollector->collect();
    $this->assertEquals(2, $this->queue->numberOfItems());

  }

  /**
   * Updates the values of a node entity and creates a new revision.
   *
   * This method sets the specified field values on the provided node entity,
   * updates its changed and revision creation timestamps, and ensures a new
   * revision is created. After saving, the node entity is reloaded.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node entity being updated. Passed by reference to retain changes.
   * @param array $values
   *   An associative array of field keys and their respective values
   *   to be set on the node entity.
   * @param int $timestamp
   *   A Unix timestamp to be used for the changed time and
   *   revision creation time of the node.
   *
   * @return void
   *   All modifications are applied directly to the input node.
   */
  protected function setNodeValues(&$node, $values, $timestamp) {
    foreach ($values as $key => $value) {
      $node->set($key, $value);
    }
    $node->setNewRevision(TRUE);
    $node->setChangedTime($timestamp);
    $node->setRevisionCreationTime($timestamp);
    $node->save();
    $node = $this->reloadEntity($node);
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
