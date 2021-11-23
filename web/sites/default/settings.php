<?php

$settings['config_sync_directory'] = '../config/sync';

$settings['hash_salt'] = 'KrHccV8K1iFq5n-WjbqIQzM40cfaUiBPL2xMXZWKv_AUALCHuLAFrKXsfzV3k8W41v7kjEyj2g';

$databases['default']['default'] = array (
  'database' => 'drupal8',
  'username' => 'drupal8',
  'password' => 'drupal8',
  'prefix' => '',
  'host' => 'database.helfimanuska.internal',
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
);

if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
