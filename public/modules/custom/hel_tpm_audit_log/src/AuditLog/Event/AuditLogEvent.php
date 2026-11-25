<?php

declare (strict_types=1);

namespace Drupal\hel_tpm_audit_log\AuditLog\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

/**
 * Event class for audit log use.
 *
 * Event subscribers may enrich the event before it is written
 * to the audit log. Origin and environment fields are injected
 * in AuditLogSourceEntry.
 *
 * @see https://helsinkisolutionoffice.atlassian.net/wiki/spaces/platta/pages/10189438980/Implementing+audit+logging
 * @see \Drupal\hel_tpm_audit_log\AuditLog\Sources\AuditLogSourceEntry::getDocument()
 * @see \Drupal\hel_tpm_audit_log\AuditLog\AuditLogService::logOperation()
 */
class AuditLogEvent extends Event {

  /**
   * Construct a new event object.
   *
   * @param string $operation
   *   The type of action performed.
   * @param string $message
   *   Descriptive information about the action.
   * @param array<string, mixed> $target
   *   The target of the operation.
   * @param array<string, mixed> $extra
   *   Optional additional structured information.
   * @param array<string, mixed> $actor
   *   Who performed the action (user or system).
   * @param \DateTimeImmutable $dateTime
   *   When the action occurred. Defaults to the current UTC time.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity the operation was performed on, if any. Lets subscribers add
   *   entity specific context; it is not written to the audit log itself.
   */
  public function __construct(
    public string $operation,
    public string $message,
    public array $target,
    public array $extra = [],
    public array $actor = [],
    public readonly \DateTimeImmutable $dateTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
    public readonly ?EntityInterface $entity = NULL,
  ) {
  }

  /**
   * Get the audit event data.
   *
   * @return array<string, mixed>
   *   The data associated with the event.
   */
  public function getData(): array {
    $data = [
      // Format should be yyyy-MM-ddThh:mm:ss.SSSZ.
      'date_time' => $this->dateTime->format('Y-m-d\TH:i:s.v\Z'),
      'operation' => $this->operation,
      'message' => $this->message,
      'target' => $this->target,
    ];
    if ($this->actor) {
      $data['actor'] = $this->actor;
    }
    if (!empty($this->extra)) {
      $data['extra'] = $this->extra;
    }
    return $data;
  }

}
