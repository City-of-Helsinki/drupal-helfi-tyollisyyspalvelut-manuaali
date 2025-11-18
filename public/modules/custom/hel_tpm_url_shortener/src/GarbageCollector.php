<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_url_shortener;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * URL Shortener garbage collector.
 */
final class GarbageCollector {

  /**
   * Represents a timestamp set to one year in the past.
   */
  protected string $timestamp = '-1 year';

  /**
   * Name of the queue used for garbage collection in URL shortener worker.
   */
  private string $queueName = 'hel_tpm_url_shortener_garbage_queue_worker';

  /**
   * Constructs a GarbageCollector object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QueueFactory $queueFactory,
    private readonly Connection $connection,
  ) {}

  /**
   * Collects and processes garbage links by adding them to a queue.
   *
   * @return void
   *   Return void.
   */
  public function collect(): void {
    $garbage = $this->collectGarbageLinks();
    if (empty($garbage)) {
      return;
    }
    $this->addToQueue($garbage);
  }

  /**
   * Collects and retrieves a list of garbage links from the database.
   *
   * The method queries the 'shortenerredirect' table to identify links
   * that have either not been used recently or have not been updated
   * within a defined timestamp. It returns an associative array of
   * results where the key is the link ID.
   *
   * @return array
   *   An associative array of garbage links with their IDs as keys.
   */
  protected function collectGarbageLinks() {
    $query = $this->connection->select('shortenerredirect', 's')
      ->fields('s', ['id']);
    $and = $query->andConditionGroup()
      ->condition('s.changed', $this->getTimestamp(), '<')
      ->condition('s.last_usage', NULL, 'IS NULL');
    $and2 = $query->andConditionGroup()
      ->condition('s.last_usage', $this->getTimestamp(), '<');
    $or = $query->orConditionGroup()
      ->condition($and)
      ->condition($and2);
    $result = $query->condition($or)
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
    return $result;
  }

  /**
   * Adds a given garbage data item to the queue for further processing.
   *
   * @param array $garbage
   *   An associative array representing the garbage data to be queued.
   *
   * @return void
   *   This method does not return a value.
   */
  protected function addToQueue(array $garbage) {
    $queue = $this->getQueue();
    $queue->createItem($garbage);
  }

  /**
   * Converts a stored timestamp into a Unix timestamp.
   *
   * @return int
   *   The Unix timestamp representation of the stored timestamp.
   */
  private function getTimestamp() {
    $datetime = new DrupalDateTime($this->timestamp);
    return $datetime->getTimestamp();
  }

  /**
   * Retrieves the queue instance associated with the specified queue name.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue instance for the given queue name.
   */
  public function getQueue() {
    return $this->queueFactory->get($this->getQueueName());
  }

  /**
   * Retrieves the name of the queue.
   *
   * @return string
   *   The name of the queue.
   */
  private function getQueueName() {
    return $this->queueName;
  }

}
