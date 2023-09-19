<?php

/**
 * @file
 * Provide views data for service_manual_workflow.module.
 *
 * @ingroup views_module_handlers
 */

use Drupal\service_manual_workflow\ViewsData;

/**
 * Implements hook_views_data().
 */
function service_manual_workflow_views_data() {
  return _service_manual_workflow_views_data_object()->getViewsData();
}

/**
 * Implements hook_views_data().
 */
/**
 * Implements hook_views_data_alter().
 */
function service_manual_workflow_views_data_alter(array &$data) {
  $data = $data;
}

/**
 * Creates a ViewsData object to respond to views hooks.
 *
 * @return \Drupal\service_manual_workflow\ViewsData
 *   The content moderation ViewsData object.
 */
function _service_manual_workflow_views_data_object() {
  return new ViewsData(
    \Drupal::service('entity_type.manager'),
    \Drupal::service('content_moderation.moderation_information')
  );
}
