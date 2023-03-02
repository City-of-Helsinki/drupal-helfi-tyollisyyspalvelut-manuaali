<?php

$save_nids = [284];

$database = \Drupal::database();
$nodeStorage = \Drupal::entityTypeManager()->getStorage('node');

$q = 'SELECT n.nid
  FROM {node} n
  WHERE n.type = :type AND n.nid NOT IN (:nids[])';
$res = $database->query($q, [':type' => 'service', ':nids[]' => $save_nids]);
$rows = $res->fetchCol();
foreach (array_chunk($rows, 10) as $nids) {
  $nodes = $nodeStorage->loadMultiple($nids);
  foreach ($nodes as $node) {
    echo 'Removing ' . $node->id() . "\n";
    $node->delete();
  }
}
