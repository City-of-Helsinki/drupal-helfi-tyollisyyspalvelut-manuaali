<?php

fix_missing_service_translations();

/**
 * Fixes missing translations for service entities by marking their revisions
 * as affected. It identifies node revisions missing translations and updates
 * them in the database to ensure proper language handling.
 *
 * @return array An associative array where each key is a node ID (nid) and its
 *   value is an array of language codes (langcodes), each associated with
 *   their respective mapped revisions.
 */
function fix_missing_service_translations() {
  $database = \Drupal::service('database');
  $services_missing_translations = service_translation_no_revisions();
  $mapped_revisions = [];
  foreach ($services_missing_translations as $nid => $langcodes) {
    foreach ($langcodes as $langcode) {
      $mapped_revisions[$nid][$langcode] = $database->select("node_field_revision", 'nfr')
        ->fields('nfr')
        ->condition('nid', $nid)
        ->condition('langcode', $langcode)
        ->execute()
        ->fetchAll();
    }
  }
  foreach ($mapped_revisions as $nid => $langcodes) {
    foreach ($langcodes as $langcode => $revisions) {
      $changed = find_changed_revisions($revisions);
      foreach ($changed as $rev) {
        $database->update("node_field_revision")
          ->fields(['revision_translation_affected' => 1])
          ->condition('vid', $rev->vid)
          ->condition('langcode', $rev->langcode)
          ->execute();
      }
      if (!empty($changed)) {
        print sprintf('%s langcode %s count: %s', $nid, $langcode, count($changed));
        print "\r\n";
      }
    }
  }
  return $mapped_revisions;
}

/**
 * Identifies and returns a list of changed revisions for a given set of
 * node revisions. A revision is considered "changed" if it has modified
 * fields compared to its previous version.
 *
 * @param \Drupal\Core\Entity\EntityInterface[] $revisions
 *   An array of node revision entities to compare. Each entity must have the
 *   'vid' (revision ID) and 'langcode' (language code) properties.
 *
 * @return \Drupal\Core\Entity\EntityInterface[]
 *   An array of node revision entities that are identified as changed.
 */
function find_changed_revisions($revisions) {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $diff_comparison = \Drupal::service('diff.entity_comparison');
  $rev = $revisions[0];

  unset($revisions[0]);
  $changed_revisions = [$rev];

  $left_entity = $storage->loadRevision($rev->vid);

  foreach ($revisions as $revision) {
    $right_entity = $storage->loadRevision($revision->vid);
    $diff = $diff_comparison->compareRevisions($left_entity, $right_entity, $rev->langcode);
    if (!has_changed_fields($diff)) {
      continue;
    }
    $changed_revisions[] = $revision;
    $left_entity = $right_entity;
  }

  return $changed_revisions;
}

/**
 * Determines whether there are changed fields in the provided diff.
 * This function compares fields while ignoring certain fields such as
 * 'uid', 'langcode', 'created', and 'changed'. It checks if field data
 * from the left side differs from the right side.
 *
 * @param array $diff
 *   An associative array representing the difference between two entities.
 *   Each key corresponds to a field, and the value is another array
 *   containing detailed difference data, including '#left' and '#right'
 *   values for comparison.
 *
 * @return bool
 *   Returns TRUE if any field data differs, excluding the ignored fields.
 *   Returns FALSE if no relevant differences are found.
 */
function has_changed_fields($diff) {
   $field_bl = ['uid', 'langcode', 'created', 'changed'];
  foreach ($diff as $field => $field_diff) {
    if (in_array($field, $field_bl)) {
      continue;
    }

    if ($field_diff['#data']['#left'] === $field_diff['#data']['#right']) {
      continue;
    }

    return TRUE;
  }
  return FALSE;
}

/**
 * Retrieves a list of service node entities with missing translated
 * revisions. It identifies nodes of type 'service' where translated
 * revisions are not marked as affected.
 *
 * @return array An associative array where each key is a node ID (nid) and its
 *   value is an array of language codes (langcodes) for which there are no
 *   revisions marked as translation affected.
 */
function service_translation_no_revisions() {
  $database = \Drupal::service('database');
  $query = $database->select('node_field_revision', 'nfr')
    ->fields('nfr');
  $query->addJoin('INNER', 'node', 'n', 'nfr.nid = n.nid');
  $query->condition('n.type', 'service');
  $node_revision_rows = $query->execute()->fetchAll();

  $translations_revisions = [];
  foreach ($node_revision_rows as $node_revision_row) {
    $translations_revisions[$node_revision_row->nid][$node_revision_row->langcode] = [];
  }
  foreach ($node_revision_rows as $node_revision_row) {
    if ($node_revision_row->revision_translation_affected != 1) {
      continue;
    }
    $translations_revisions[$node_revision_row->nid][$node_revision_row->langcode][] = $node_revision_row;
  }

  foreach ($translations_revisions as $nid => $translations) {
    foreach ($translations as $langcode => $revisions) {
      if (empty($revisions)) {
        continue;
      }
      unset($translations_revisions[$nid][$langcode]);
    }
  }
  foreach ($translations_revisions as $nid => $translations) {
    if (empty($translations)) {
      unset($translations_revisions[$nid]);
      continue;
    }
    $translations_revisions[$nid] = array_keys($translations);
  }
  return $translations_revisions;
}