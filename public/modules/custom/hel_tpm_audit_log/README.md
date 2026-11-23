# Hel TPM Audit log

Audit logging ported from
[helfi_api_base](https://github.com/City-of-Helsinki/drupal-module-helfi-api-base)
(`src/AuditLog`, `src/Hook/AuditLog*`, `src/Drush/Commands/AuditLog*`).
Behaviour, table name (`helfi_audit_logs`), settings keys and Drush command
names are the same as upstream.

## What is logged

- `ENTITY_CREATE`, `ENTITY_UPDATE`, `ENTITY_DELETE` (and optionally
  `ENTITY_READ`) for entity types listed in the
  `hel_tpm_audit_log.audit_log_entity_types` service parameter
  (default: `node`, `user`). Updates of revisionable content entities include
  a human-readable field diff (`extra.ContentDiff`, via the diff module).
- `USER_LOGIN` and `USER_LOGOUT`.
- Anything custom code passes to
  `AuditLogServiceInterface::logOperation(new AuditLogEvent(...))`.

Events are queued and written to the `helfi_audit_logs` table at the end of
the request. `AuditLogActorSubscriber` adds the current user (roles, uid, IP)
and `AuditLogRequestIdSubscriber` adds the request id.

Override the entity types in a site `services.yml`:

```yaml
parameters:
  hel_tpm_audit_log.audit_log_entity_types:
    - { entity_type: 'node', bundle: 'service' }
    - { entity_type: 'user', operations: ['ENTITY_CREATE', 'ENTITY_DELETE'] }
```

Disable the entity hooks (e.g. during migrations):

```php
$settings['auditlog_entity_hooks_disable'] = TRUE;
```

## Shipping logs

Rows are shipped by
[php-resilient-logger](https://github.com/City-of-Helsinki/php-resilient-logger),
configured in `settings.php`:

```php
$settings['resilient_logger'] = [
  'sources' => [
    ['class' => \Drupal\hel_tpm_audit_log\AuditLog\Sources\AuditLogSource::class],
  ],
  'targets' => [
    [
      'class' => \ResilientLogger\Targets\ElasticsearchLogTarget::class,
      'es_url' => getenv('AUDIT_LOG_ES_URL'),
      'es_username' => getenv('AUDIT_LOG_ES_USERNAME'),
      'es_password' => getenv('AUDIT_LOG_ES_PASSWORD'),
      'es_index' => getenv('AUDIT_LOG_ES_INDEX'),
      'required' => TRUE,
    ],
  ],
  'origin' => 'palvelumanuaali',
  // Optional: defaults to the PLATFORM_ENVIRONMENT_TYPE env variable.
  'environment' => 'production',
  'store_old_entries_days' => 30,
];
```

Unlike helfi_api_base, `origin`/`environment` are not resolved from the helfi
environment resolver: set them in the setting (`environment` falls back to
`PLATFORM_ENVIRONMENT_TYPE`).

## Drush commands

Run these from cron:

- `drush helfi:audit-log:submit-unsent-entries` – send unsent rows to the
  targets. Exits non-zero if any entry fails.
- `drush helfi:audit-log:clear-sent-entries` – delete sent rows older than
  `store_old_entries_days`.

Both are no-ops when `resilient_logger` is not configured.
