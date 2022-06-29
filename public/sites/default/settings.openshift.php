<?php

$databases['default']['default'] = [
  'database' => getenv('DRUPAL_DB_NAME'),
  'username' => getenv('DRUPAL_DB_USER'),
  'password' => getenv('DRUPAL_DB_PASS'),
  'prefix' => '',
  'host' => getenv('DRUPAL_DB_HOST'),
  'port' => getenv('DRUPAL_DB_PORT') ?: 3306,
  'namespace' => 'Drupal\Core\Database\Driver\mysql',
  'driver' => 'mysql',
];

$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: '000';

if ($ssl_ca_path = getenv('AZURE_SQL_SSL_CA_PATH')) {
  $databases['default']['default']['pdo'] = [
    \PDO::MYSQL_ATTR_SSL_CA => $ssl_ca_path,
    \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => FALSE,
  ];

  // Azure specific filesystem fixes.
  $settings['php_storage']['twig']['directory'] = '/tmp';
  $settings['php_storage']['twig']['secret'] = $settings['hash_salt'];
  $settings['file_chmod_directory'] = 16895;
  $settings['file_chmod_file'] = 16895;

  $config['system.performance']['cache']['page']['max_age'] = 86400;
}

$elasticsearch_env_name = 'ELASTICSEARCH_TYOLLISYYSPTV_' . strtoupper(getenv('REDIS_PREFIX')) . '_ES_INTERNAL_HTTP_PORT';
if (getenv($elasticsearch_env_name)) {
  $config['elasticsearch_connector.cluster.search']['url'] = str_replace('tcp:', 'https:', getenv($elasticsearch_env_name));
  $config['elasticsearch_connector.cluster.search']['options']['username'] = getenv('ELASTICSEARCH_USER');
  $config['elasticsearch_connector.cluster.search']['options']['password'] = getenv('ELASTICSEARCH_PASSWORD');
  $config['elasticsearch_connector.cluster.search']['options']['insecure'] = TRUE;
}
