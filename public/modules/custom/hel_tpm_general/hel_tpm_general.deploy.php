<?php

/**
 * @file
 * Deploy functions for Hel TPM General.
 */

/**
 * Rebuild node access after upgrading the Nodeaccess contrib module.
 */
function hel_tpm_general_deploy_0001(array &$sandbox): void {
  node_access_rebuild();
}

/**
 * Migrate 'accessibility details' field to new field supporting text formats.
 */
function hel_tpm_general_deploy_0002(array &$sandbox): void {
  $database = \Drupal::database();

  $tables = [
    'node__field_accessibility_details' => 'node__field_accessibility_info',
    'node_revision__field_accessibility_details' => 'node_revision__field_accessibility_info',
  ];

  foreach ($tables as $original_table => $new_table) {
    $select = $database->select($original_table, 'original');
    $select->fields('original', [
      'bundle',
      'deleted',
      'entity_id',
      'revision_id',
      'langcode',
      'delta',
    ]);
    $select->addExpression('original.field_accessibility_details_value', 'field_accessibility_info_value');
    $select->addExpression(':format', 'field_accessibility_info_format', [
      ':format' => 'plain_text_format',
    ]);

    $database->insert($new_table)
      ->from($select)
      ->execute();
  }
}
