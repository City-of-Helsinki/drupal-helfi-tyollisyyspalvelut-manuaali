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
$settings['file_private_path'] = getenv('LANDO_MOUNT') . '/private';
$settings['simple_environment_indicator'] = '#00bdbd Lando';

// Enable css and js preprocessing.
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

$settings['resilient_logger'] = [
  'sources' => [
    ['class' => \Drupal\hel_tpm_audit_log\AuditLog\Sources\AuditLogSource::class],
  ],
  'targets' => [
    [
      'class' => \ResilientLogger\Targets\ElasticsearchLogTarget::class,
      'es_url' => 'http://elastic:9200',
      'es_username' => '',
      'es_password' => '',
      'es_index' => 'audit-log-local',
      'required' => TRUE,
    ],
  ],
  'origin' => 'palvelumanuaali',
  'environment' => 'local',
  'store_old_entries_days' => 30,
];

include_once $app_root . '/' . $site_path . '/settings.redis.php';
