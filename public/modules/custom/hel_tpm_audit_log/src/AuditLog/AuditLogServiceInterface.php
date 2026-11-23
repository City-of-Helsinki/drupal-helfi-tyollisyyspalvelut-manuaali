<?php

declare (strict_types=1);

namespace Drupal\hel_tpm_audit_log\AuditLog;

use Drupal\hel_tpm_audit_log\AuditLog\Event\AuditLogEvent;

/**
 * Interface for AuditLogServices.
 */
interface AuditLogServiceInterface {

  /**
   * Dispatch AuditLogEvent and write the resulting message to the database.
   *
   * @param \Drupal\hel_tpm_audit_log\AuditLog\Event\AuditLogEvent $event
   *   The audit log event to log.
   */
  public function logOperation(AuditLogEvent $event): void;

}
