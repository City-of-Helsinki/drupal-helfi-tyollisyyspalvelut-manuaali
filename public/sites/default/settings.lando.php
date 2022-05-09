<?php
/**
 * @file
 * Lando settings.
 */

$lando_data = json_decode($_ENV['LANDO_INFO']);
$database_creds = $lando_data->database->creds;

// Set the database creds
$databases['default']['default'] = [
  'database' => $database_creds->database,
  'username' => $database_creds->user,
  'password' => $database_creds->password,
  'host' => $lando_data->database->internal_connection->host,
  'port' => '3306',
  'driver' => 'mysql'
];

// And a bogus hashsalt for now
$settings['hash_salt'] = json_encode($databases);
