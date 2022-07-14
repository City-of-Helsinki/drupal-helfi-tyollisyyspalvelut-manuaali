<?php

/**
 * @file
 * Platform.sh example settings.php file for Drupal 8.
 */

// Default Drupal 8 settings.
//
// These are already explained with detailed comments in Drupal's
// default.settings.php file.
//
// See https://api.drupal.org/api/drupal/sites!default!default.settings.php/8

$settings['update_free_access'] = FALSE;
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';
$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

$settings['config_sync_directory'] = '../config/sync';

if (getenv('SYSTEM_SITE_FRONT')) {
$config['system.site']['page']['front'] = getenv('SYSTEM_SITE_FRONT');
}

// Automatic Platform.sh settings.
if (getenv('PLATFORM_VARIABLES')) {
  include $app_root . '/' . $site_path . '/settings.platformsh.php';
}

// Openshift settings.
if (getenv('OPENSHIFT_BUILD_NAMESPACE')) {
  include $app_root . '/' . $site_path . '/settings.openshift.php';
}

// Lando settings.
if (isset($_ENV['LANDO_INFO'])) {
  include $app_root . '/' . $site_path . '/settings.lando.php';
}
// Local settings. These come last so that they can override anything.
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
