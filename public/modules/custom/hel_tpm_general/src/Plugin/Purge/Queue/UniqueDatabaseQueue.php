<?php

namespace Drupal\hel_tpm_general\Plugin\Purge\Queue;

use Drupal\purge\Plugin\Purge\Queue\DatabaseQueue;
use Drupal\purge\Plugin\Purge\Queue\QueueInterface;

/**
 * A QueueInterface compliant database backed queue.
 *
 * @PurgeQueue(
 *   id = "database_unique",
 *   label = @Translation("Database Unique queue"),
 *   description = @Translation("A scalable database backed queue."),
 * )
 */
class UniqueDatabaseQueue extends DatabaseQueue implements QueueInterface {

  /**
   * The active Drupal database connection object.
   */
  const TABLE_NAME = 'purge_queue_unique';

  /**
   * {@inheritdoc}
   */
  public function createItem($data) {
    $serialized_data = serialize($data);
    if ($this->isDuplicate($serialized_data)) {
      return FALSE;
    }
    $query = $this->connection->insert(static::TABLE_NAME)
      ->fields([
        'data' => $serialized_data,
        'created' => time(),
      ]);
    if ($id = $query->execute()) {
      return (int) $id;
    }
    return FALSE;
  }

  /**
   * Checks if the given data already exists in the database table.
   *
   * @param mixed $data
   *   The data to check for duplication.
   *
   * @return bool
   *   Returns TRUE if the data is a duplicate, FALSE otherwise.
   */
  protected function isDuplicate($data) {
    $count = $this->connection->select(static::TABLE_NAME, 'n')
      ->fields('n', [])
      ->condition('n.data', $data)
      ->countQuery()
      ->execute()->fetchField();
    return $count > 0;
  }

  /**
   * Removes duplicate arrays from the provided array of items.
   *
   * Given an array of arrays, this method eliminates
   * duplicates based on their serialized representation,
   * ensuring that only unique arrays remain in the original input array.
   *
   * @param array &$items
   *   The array of items to de-duplicate, passed by reference.
   *   This array will be modified in place to only contain unique arrays.
   *
   * @return void
   *   No value is returned; the input array is modified directly.
   */
  protected function deDuplicateItems(array &$items) {
    // Filter out identical arrays from the items array.
    $items = array_map('unserialize', array_unique(array_map('serialize', $items)));
  }

  /**
   * {@inheritdoc}
   */
  public function createItemMultiple(array $items) {
    $item_ids = $records = [];

    // Build a array with all exactly records as they should turn into rows.
    $time = time();

    $this->deDuplicateItems($items);

    foreach ($items as $data) {
      $serialized_data = serialize($data);
      if ($this->isDuplicate($serialized_data)) {
        continue;
      }
      $records[] = [
        'data' => $serialized_data,
        'created' => $time,
      ];
    }

    if (empty($records)) {
      return FALSE;
    }

    // Insert all of them using just one multi-row query.
    $query = $this->connection
      ->insert(static::TABLE_NAME, [])
      ->fields(['data', 'created']);
    foreach ($records as $record) {
      $query->values($record);
    }

    // Execute the query and finish the call.
    if ($id = $query->execute()) {
      $id = (int) $id;

      // A multiple row-insert doesn't give back all the individual IDs, so
      // calculate them back by applying subtraction.
      for ($i = 1; $i <= count($records); $i++) {
        $item_ids[] = $id;
        $id++;
      }
      return $item_ids;
    }
    else {
      return FALSE;
    }
  }

}
