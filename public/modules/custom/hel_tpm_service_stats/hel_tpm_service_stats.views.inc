<?php

/**
 * @file
 * Hel_tpm_service_stats views file.
 */

/**
 * Implements hook_views_data_alter().
 */
function hel_tpm_service_stats_views_data_alter(array &$data) {
  // Add the current company computed field to Views.
  $data['service_published_row']['publish_interval'] = [
    'title' => t('Time between changed states'),
    'field' => [
      'title' => t('Time between changed states'),
      'id' => 'hel_tpm_service_stats_publish_interval',
    ],
  ];
  $data['node']['time_since_last_state_change'] = [
    'title' => t('Days since last state change'),
    'field' => [
      'id' => 'hel_tpm_service_stats_time_since_last_state_change',
    ],
  ];
}
