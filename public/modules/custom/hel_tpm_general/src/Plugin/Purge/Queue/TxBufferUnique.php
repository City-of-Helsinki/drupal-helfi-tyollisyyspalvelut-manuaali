<?php

namespace Drupal\hel_tpm_general\Plugin\Purge\Queue;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\purge\Plugin\Purge\Queue\ProxyItem;
use Drupal\purge\Plugin\Purge\Queue\TxBuffer;
use Drupal\purge\Plugin\Purge\Queue\TxBufferInterface;

/**
 * Provides a unique transaction buffer implementation by removing duplicates.
 */
final class TxBufferUnique extends TxBuffer {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $connection;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * Plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  private PluginManagerInterface $pluginManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(PluginManagerInterface $pluginManager, Connection $connection, ConfigFactoryInterface $configFactory) {
    $this->pluginManager = $pluginManager;
    $this->connection = $connection;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function getFiltered($states) {
    $results = parent::getFiltered($states);
    if ($states !== TxBufferInterface::ADDING) {
      return $results;
    }

    foreach ($results as $key => $result) {
      if ($this->isDuplicate($result)) {
        unset($results[$key]);
      }
    }

    return $results;
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
    $serialized_data = $this->getItemData($data);
    $count = $this->connection->select($this->getPurgeQueueTable(), 'n')
      ->fields('n', [])
      ->condition('n.data', $serialized_data)
      ->countQuery()
      ->execute()->fetchField();
    return $count > 0;
  }

  /**
   * Retrieves and serializes the data from the given input.
   *
   * @param mixed $data
   *   The input data to process.
   *
   * @return string
   *   A serialized string representation of the processed data.
   */
  private function getItemData($data) {
    $getProxiedData = function ($invalidation) {
      $proxy = new ProxyItem($invalidation, $this);
      return $proxy->data;
    };
    return serialize($getProxiedData($data));
  }

  /**
   * Retrieves the name of the purge queue table.
   *
   * @return string
   *   The name of the purge queue database table.
   */
  private function getPurgeQueueTable() : string {
    $table = &drupal_static(__CLASS__ . __METHOD__);

    if (empty($table)) {
      $plugin_id = $this->configFactory->get('purge.plugins')->get('queue');
      $table = $this->pluginManager->createInstance($plugin_id)::TABLE_NAME;
    }

    return $table;
  }

}
