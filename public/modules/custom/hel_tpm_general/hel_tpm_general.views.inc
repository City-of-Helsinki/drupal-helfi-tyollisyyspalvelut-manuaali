<?php

/**
 * Implements hook_views_data_alter().
 */
function hel_tpm_general_views_data_alter(array &$data) {
  $data['groups']['group_super_groups'] = array(
    'title' => t('Group Super Groups'),
    'group' => t('Groups'),
    'field' => array(
      'title' => t('Group Super Groups'),
      'help' => t('Shows subgroups super groups.'),
      'id' => 'hel_tpm_general_group_super_groups',
    ),
  );
}
