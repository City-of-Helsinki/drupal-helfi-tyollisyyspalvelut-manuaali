<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_file_garbage_collector\Traits;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Queue\QueueWorkerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\hel_tpm_file_garbage_collector\FileGarbageCollector;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Fixtures and helpers for file garbage collector kernel tests.
 *
 * Requires the modules file, image, node, entity_reference_revisions,
 * paragraphs and hel_tpm_file_garbage_collector, and a test class extending
 * \Drupal\KernelTests\Core\Entity\EntityKernelTestBase.
 */
trait FileGarbageCollectorTestTrait {

  /**
   * Garbage collector service.
   *
   * @var \Drupal\hel_tpm_file_garbage_collector\FileGarbageCollector
   */
  protected FileGarbageCollector $garbageCollector;

  /**
   * Garbage collector queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Installs schemas, an article type with file fields and a file paragraph.
   *
   * The article has the fields file_test (file), image_test (image) and
   * paragraphs_test (paragraphs of type file_paragraph with the file field
   * paragraph_file).
   */
  protected function setUpFileGarbageCollector(): void {
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['system', 'image', 'node', 'hel_tpm_file_garbage_collector']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
      'new_revision' => TRUE,
    ])->save();
    ParagraphsType::create([
      'id' => 'file_paragraph',
      'label' => 'File paragraph',
    ])->save();

    $this->createField('node', 'article', 'file_test', 'file');
    $this->createField('node', 'article', 'image_test', 'image');
    $this->createField('node', 'article', 'paragraphs_test', 'entity_reference_revisions', ['target_type' => 'paragraph']);
    $this->createField('paragraph', 'file_paragraph', 'paragraph_file', 'file');

    $this->garbageCollector = $this->container->get('hel_tpm_file_garbage_collector.collector');
    $this->queue = $this->container->get('queue')->get(FileGarbageCollector::$queue);
  }

  /**
   * Creates a field storage and field.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   * @param string $type
   *   The field type.
   * @param array $settings
   *   (optional) Field storage settings.
   */
  protected function createField(string $entity_type, string $bundle, string $field_name, string $type, array $settings = []): void {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => $type,
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => $settings,
    ])->save();
    FieldConfig::create([
      'entity_type' => $entity_type,
      'field_name' => $field_name,
      'bundle' => $bundle,
      'translatable' => FALSE,
    ])->save();
  }

  /**
   * Creates a permanent text file.
   *
   * @param string $filename
   *   The file name.
   *
   * @return \Drupal\file\FileInterface
   *   The file entity.
   */
  protected function createTextFile(string $filename): FileInterface {
    $uri = 'public://' . $filename;
    file_put_contents($uri, $this->randomMachineName());
    $file = File::create(['uri' => $uri]);
    $file->setPermanent();
    $file->save();
    return $file;
  }

  /**
   * Creates a permanent image file.
   *
   * @param string $filename
   *   The file name.
   *
   * @return \Drupal\file\FileInterface
   *   The file entity.
   */
  protected function createImageFile(string $filename): FileInterface {
    $uri = 'public://' . $filename;
    copy($this->root . '/core/tests/fixtures/files/image-1.png', $uri);
    $file = File::create(['uri' => $uri]);
    $file->setPermanent();
    $file->save();
    return $file;
  }

  /**
   * Creates a published article.
   *
   * @param array $values
   *   Field values.
   * @param string $time
   *   Relative time used for the timestamps.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  protected function createNode(array $values, string $time): NodeInterface {
    $timestamp = (new DrupalDateTime($time))->getTimestamp();
    $node = Node::create($values + [
      'title' => $this->randomMachineName(),
      'type' => 'article',
      'status' => 1,
      'created' => $timestamp,
      'changed' => $timestamp,
      'revision_timestamp' => $timestamp,
    ]);
    $node->save();
    return $node;
  }

  /**
   * Saves a new default revision of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param array $values
   *   Field values to set.
   * @param string $time
   *   Relative time used for the timestamps.
   *
   * @return \Drupal\node\NodeInterface
   *   The reloaded node.
   */
  protected function saveNewRevision(NodeInterface $node, array $values, string $time): NodeInterface {
    $node = $this->reloadEntity($node);
    foreach ($values as $key => $value) {
      $node->set($key, $value);
    }
    $node->setNewRevision(TRUE);
    $this->setTimestamps($node, $time);
    $node->save();
    return $this->reloadEntity($node);
  }

  /**
   * Replaces the file of the node's paragraph in a new host revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The host node.
   * @param \Drupal\file\FileInterface $file
   *   The new file.
   * @param string $time
   *   Relative time used for the timestamps.
   *
   * @return \Drupal\node\NodeInterface
   *   The reloaded node.
   */
  protected function replaceParagraphFile(NodeInterface $node, FileInterface $file, string $time): NodeInterface {
    $node = $this->reloadEntity($node);
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $paragraph = $node->get('paragraphs_test')->entity;
    $paragraph->set('paragraph_file', [$file->id()]);
    $paragraph->setNeedsSave(TRUE);
    $node->set('paragraphs_test', [$paragraph]);
    $node->setNewRevision(TRUE);
    $this->setTimestamps($node, $time);
    $node->save();
    return $this->reloadEntity($node);
  }

  /**
   * Sets the changed and revision timestamps of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $time
   *   Relative time.
   */
  protected function setTimestamps(NodeInterface $node, string $time): void {
    $timestamp = (new DrupalDateTime($time))->getTimestamp();
    $node->setChangedTime($timestamp);
    $node->setRevisionCreationTime($timestamp);
  }

  /**
   * Overrides the changed timestamp of the current node revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $time
   *   Relative time.
   */
  protected function setRevisionTimestamps(NodeInterface $node, string $time): void {
    $this->container->get('database')->update('node_field_revision')
      ->fields(['changed' => (new DrupalDateTime($time))->getTimestamp()])
      ->condition('vid', $node->getRevisionId())
      ->execute();
  }

  /**
   * Gets the file_test target IDs of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return array
   *   List of items with 'target_id'.
   */
  protected function getTargetIds(NodeInterface $node): array {
    return array_map(
      static fn (array $item) => ['target_id' => (int) $item['target_id']],
      $node->get('file_test')->getValue(),
    );
  }

  /**
   * Claims all queued items and returns their file IDs.
   *
   * @return int[]
   *   Sorted list of queued file IDs.
   */
  protected function getQueuedFids(): array {
    $fids = [];
    while ($item = $this->queue->claimItem()) {
      $fids[] = (int) $item->data['fid'];
      $this->queue->deleteItem($item);
    }
    sort($fids);
    return $fids;
  }

  /**
   * Gets the garbage collector queue worker.
   *
   * @return \Drupal\Core\Queue\QueueWorkerInterface
   *   The queue worker.
   */
  protected function getWorker(): QueueWorkerInterface {
    return $this->container->get('plugin.manager.queue_worker')->createInstance(FileGarbageCollector::$queue);
  }

}
