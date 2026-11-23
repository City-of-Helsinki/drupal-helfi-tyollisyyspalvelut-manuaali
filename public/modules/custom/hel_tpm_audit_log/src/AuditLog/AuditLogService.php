<?php

declare (strict_types=1);

namespace Drupal\hel_tpm_audit_log\AuditLog;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Utility\Error;
use Drupal\hel_tpm_audit_log\AuditLog\Event\AuditLogEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * AuditLog service.
 */
class AuditLogService implements AuditLogServiceInterface, DestructableInterface {

  /**
   * The queue.
   *
   * @var array<\Drupal\hel_tpm_audit_log\AuditLog\Event\AuditLogEvent>
   */
  private array $queue = [];

  /**
   * Constructs a AuditLogService object.
   */
  public function __construct(
    private readonly Connection $connection,
    private readonly TimeInterface $time,
    private readonly EventDispatcherInterface $eventDispatcher,
    #[Autowire(service: 'logger.channel.hel_tpm_audit_log')]
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Dispatch AuditLogEvent and write the resulting message to the database.
   *
   * @param \Drupal\hel_tpm_audit_log\AuditLog\Event\AuditLogEvent $event
   *   The audit log event to log.
   */
  public function logOperation(AuditLogEvent $event): void {
    $this->eventDispatcher->dispatch($event);
    $this->queue[] = $event;
  }

  /**
   * Process the given event.
   *
   * @param \Drupal\hel_tpm_audit_log\AuditLog\Event\AuditLogEvent $event
   *   The event to process.
   */
  public function processItem(AuditLogEvent $event): void {
    try {
      $this->connection->insert('helfi_audit_logs')
        ->fields([
          'created_at' => gmdate('Y-m-d H:i:s', $this->time->getRequestTime()),
          'is_sent' => 0,
          'message' => Json::encode(['audit_event' => $event->getData()]),
        ])
        ->execute();
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    foreach ($this->queue as $event) {
      $this->processItem($event);
    }
    $this->queue = [];
  }

}
