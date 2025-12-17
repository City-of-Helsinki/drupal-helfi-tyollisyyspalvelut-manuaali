<?php

/**
 * Deletes flagging entries associated with anonymous users.
 */
$limit = 1000;
$database = \Drupal::database();
$count = 0;
$max = \Drupal::database()->select('flagging', 'f')
  ->fields('f')
  ->condition('f.uid', 0)
  ->countQuery()
  ->execute()->fetchField();
while($count <= $max) {
  $flags = $database->select('flagging', 'f')
    ->fields('f', ['id'])
    ->condition('f.uid', 0)
    ->range(0, $limit)
    ->execute()
    ->fetchAllAssoc('id');

  $database->delete('flagging')
    ->condition('id', array_keys($flags), 'IN')
    ->execute();
  $count = $count + $limit;
}

