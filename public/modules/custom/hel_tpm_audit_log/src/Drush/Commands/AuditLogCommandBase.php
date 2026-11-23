<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_audit_log\Drush\Commands;

use Drupal\Core\Site\Settings;
use Drush\Commands\AutowireTrait;
use ResilientLogger\ResilientLogger;
use Symfony\Component\Console\Command\Command;

/**
 * A base class for audit log drush commands.
 *
 * @phpstan-import-type ResilientLoggerOptions from \ResilientLogger\Types as LoggerOptions
 */
abstract class AuditLogCommandBase extends Command {

  use AutowireTrait;

  public function __construct(
    private readonly Settings $settings,
  ) {
    parent::__construct();
  }

  /**
   * Creates the resilient logger from settings.
   *
   * The 'origin' and 'environment' options are read from the
   * 'resilient_logger' setting. When 'environment' is not set, the
   * Platform.sh environment type is used instead.
   *
   * @return \ResilientLogger\ResilientLogger|null
   *   The resilient logger or NULL if the audit log is not configured.
   */
  protected function getResilientLogger() : ?ResilientLogger {
    /** @var LoggerOptions $options */
    $options = $this->settings->get('resilient_logger', []);

    if (!$options) {
      return NULL;
    }

    if (empty($options['environment']) && $environment = getenv('PLATFORM_ENVIRONMENT_TYPE')) {
      $options['environment'] = $environment;
    }

    return ResilientLogger::create($options);
  }

}
