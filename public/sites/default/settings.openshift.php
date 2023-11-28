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

if ($es_username = getenv('ELASTICSEARCH_USER')) {
  // Unfortunately there apparently aren't sensible ways of fetching this...
  $regexp = '%([a-z]+\.hel\.fi)%';
  preg_match($regexp, getenv('DRUPAL_REVERSE_PROXY_ADDRESS'), $hostname_parts);
  $config['elasticsearch_connector.cluster.search']['url'] = 'https://elasticsearch-hki-kanslia-tyollisyysptv-' . getenv('REDIS_PREFIX') . '.apps.' . $hostname_parts[1] . ':443';
  $config['elasticsearch_connector.cluster.search']['options']['use_authentication'] = TRUE;
  $config['elasticsearch_connector.cluster.search']['options']['username'] = $es_username;
  if ($password = getenv($es_username)) {
    $config['elasticsearch_connector.cluster.search']['options']['password'] = $password;
  }
}

if ($app_env = getenv('APP_ENV')) {
  switch($app_env) {
    case 'development':
      $settings['simple_environment_indicator'] = '#004984 Development';
      break;
    case 'staging':
      $settings['simple_environment_indicator'] = '#e56716 Stage';
      break;
    case 'production':
      $settings['simple_environment_indicator'] = '#d4000f Production';
      break;
  }
}

if ($solr_host = getenv('SOLR_SERVICE_HOST')) {
  $config['search_api.server.solr_search']['backend_config']['connector_config']['core'] = 'dev';
  $config['search_api.server.solr_search']['backend_config']['connector_config']['host'] = 'solr';
}

if (getenv('REDIS_HOST')) {
  $settings['redis.connection']['interface'] = 'PhpRedis'; // Can be "Predis".
  $settings['redis.connection']['host']      = [getenv('REDIS_HOST')];
  $settings['redis.connection']['instance']  = getenv('REDIS_INSTANCE');
  $settings['redis.connection']['password'] = getenv('REDIS_PASSWORD');
  $settings['cache']['default'] = 'cache.backend.redis';
  $settings['cache_prefix'] = getenv('REDIS_PREFIX') . '_';
}

if (getenv('DRUPAL_VARNISH_HOST') && getenv('DRUPAL_VARNISH_PORT')) {
  $config['varnish_purger.settings.2ce1889afd']['hostname'] = getenv('DRUPAL_VARNISH_HOST');
  $config['varnish_purger.settings.2ce1889afd']['port'] = getenv('DRUPAL_VARNISH_PORT');
}

if (getenv('SMTP_HOST')) {
  $config['smtp.settings']['smtp_host'] = getenv('SMTP_HOST');
}
$config['user.settings']['password_reset_timeout'] = 604800;
