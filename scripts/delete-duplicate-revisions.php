<?php

cleanAnonymousRevisions();

function cleanAnonymousRevisions() {
  $fixed_nids = [];
  $revision_array = [];
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $database = \Drupal::service('database');

  $query = $database->select('node_revision', 'n')
    ->fields('n', [
      'nid',
      'vid',
      'revision_timestamp',
      'langcode',
      'revision_uid',
      'revision_default'
    ]);
  $query->addJoin('INNER', 'node_field_revision', 'nfr', 'n.vid = nfr.vid');
  $revisions = $query->fields('nfr', ['langcode', 'revision_translation_affected'])
    ->condition('nfr.revision_translation_affected', 1)
    ->execute()
    ->fetchAll();

  $revision_delete_array = [];
  foreach ($revisions as $revision) {
    $revision_array[$revision->revision_timestamp][$revision->nid][$revision->vid] = $revision;
  }

  foreach ($revision_array as $timestamp => $node_row) {
    foreach ($node_row as $nid => $revisions) {
      if (count($revisions) <= 1) {
        unset($revision_array[$timestamp][$nid]);
      }
    }
    if (empty($revision_array[$timestamp])) {
      unset($revision_array[$timestamp]);
    }
    else {
      // Remove oldest revision from revision array and
      // set the rest to deletion array.
      ksort($revisions, SORT_NUMERIC);
      $key = key($revisions);
      unset($revisions[$key]);
      $revision_delete_array = array_merge($revision_delete_array, $revisions);
    }
  }
  // Remove all revisions made by other
  // than anonymous users from deletion array.
  foreach ($revision_delete_array as $key => $revision) {
    if ($revision->revision_uid != 0) {
      unset($revision_delete_array[$key]);
    }
  }

  $deleted = [];
  foreach ($revision_delete_array as $row) {
    if ($row->vid < (int)27900) {
      continue;
    }
    $revision = $storage->loadRevision($row->vid);
    if ($revision->isDefaultRevision()) {
      print sprintf("Didn't delete the default revision: %s nid: %s \r\n", $row->vid, $row->nid);
      continue;
    }
    $storage->deleteRevision($revision->getRevisionId());
    $deleted[$row->vid] = $row;
    $fixed_nids[$row->nid] = $row->nid;
  }
  ksort($deleted, SORT_NUMERIC);

  ksort($fixed_nids, SORT_NUMERIC);

  print sprintf("Deleted %s revisions \r\n", count($deleted));
  print sprintf('Fixed following nids: %s', implode("\r\n", $fixed_nids));
}
