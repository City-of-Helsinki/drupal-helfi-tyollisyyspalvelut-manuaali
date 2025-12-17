<?php

/**
 * Deletes flagging entries associated with anonymous users.
 */
$limit = 1000;
$database = \Drupal::database();
$max = \Drupal::database()->select('flagging', 'f')
  ->fields('f')
  ->condition('f.uid', 0)
  ->countQuery()
  ->execute()->fetchField();
for ($i = 0; $i <= $max; $i++) {
  $flags = $database->select('flagging', 'f')
    ->fields('f', ['id'])
    ->condition('f.uid', 0)
    ->range(0, $limit)
    ->execute()
    ->fetchAllAssoc('id');

  $database->delete('flagging')
    ->condition('id', array_keys($flags), 'IN')
    ->execute();
  $i = $i+$limit;
}

