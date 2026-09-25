<?php

$database = \Drupal::service('database');
$cm_field_revision = $database->select('content_moderation_state_field_revision', 'cm')
  ->fields('cm')
  ->condition('cm.revision_translation_affected', 1)
  ->orderBy('cm.content_entity_id', 'ASC')
  ->execute()
  ->fetchAll();
$count = 0;
$fixed_nodes = [];
foreach ($cm_field_revision as $row) {
  $query = $database->select('node_field_revision', 'nfr')
    ->fields('nfr')
    ->condition('nfr.vid', $row->content_entity_revision_id)
    ->condition('nfr.langcode', $row->langcode);
  $or = $query->orConditionGroup()
    ->condition('nfr.revision_translation_affected', $row->revision_translation_affected, '!=')
    ->condition('nfr.revision_translation_affected', NULL, 'IS NULL');
  $node_revision_rows = $query->condition($or)
    ->execute()
    ->fetchAll();
  if (empty($node_revision_rows)) {
    continue;
  }
  foreach ($node_revision_rows as $node_revision_row) {
    $node_update = 0;
    $is_default_revision = $database->select('node', 'n')
      ->fields('n')
      ->condition('n.vid', $node_revision_row->vid)
      ->condition('n.langcode', $node_revision_row->langcode)
      ->countQuery()
      ->execute()
      ->fetchField();
    if ($is_default_revision <= 0) {
      continue;
    }
    $has_revision_translation_affected = $database->select('node_field_data', 'nfd')
      ->fields('nfd')
      ->condition('nfd.revision_translation_affected', 1)
      ->condition('nfd.vid', $node_revision_row->vid)
      ->countQuery()
      ->execute()
      ->fetchField();
    if ($has_revision_translation_affected > 0) {
      continue;
    }
    $fixed_nodes[$node_revision_row->nid][] = $node_revision_row->vid;
    $database->update('node_field_revision')
      ->condition('vid', $node_revision_row->vid)
      ->condition('langcode', $node_revision_row->langcode)
      ->fields(['revision_translation_affected' => $row->revision_translation_affected])
      ->execute();
    $database->update('node_field_data')
      ->condition('nid', $row->content_entity_id)
      ->condition('langcode', $row->langcode)
      ->fields(['revision_translation_affected' => $row->revision_translation_affected])
      ->execute();

    // Fix other languages
    $node_update = $database->update('node_field_data')
      ->condition('nid', $row->content_entity_id)
      ->condition('langcode', $row->langcode, '!=')
      ->condition('revision_translation_affected', 1)
      ->fields(['revision_translation_affected' => 0])
      ->execute();
    $count++;
    if ($node_update > 0) {
      print sprintf("Fixed %s rows node_field_data for nid %s \r\n", $node_update, $row->content_entity_id);
    }
  }
}
foreach ($fixed_nodes as $nid => $rows) {
  sort($rows);
  $fixed_nodes[$nid] = $rows;
}
print sprintf("Fixed %s nodes \r\n", $count);
print_r(array_keys($fixed_nodes));
