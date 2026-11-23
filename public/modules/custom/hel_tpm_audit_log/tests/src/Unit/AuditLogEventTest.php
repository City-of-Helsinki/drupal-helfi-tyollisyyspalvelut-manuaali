<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_audit_log\Unit;

use Drupal\hel_tpm_audit_log\AuditLog\Event\AuditLogEvent;
use Drupal\hel_tpm_audit_log\Hook\AuditLogEntityHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests AuditLogEvent object.
 */
#[Group('hel_tpm_audit_log')]
class AuditLogEventTest extends UnitTestCase {

  /**
   * Test that event data can be retrieved.
   */
  public function testCreateEvent() : void {
    $event = new AuditLogEvent(AuditLogEntityHooks::ENTITY_CREATE, 'SUCCESS', ['id' => '1']);
    $this->assertEquals('ENTITY_CREATE', $event->getData()['operation']);
    $this->assertEquals('SUCCESS', $event->getData()['message']);
    $this->assertEquals(['id' => '1'], $event->getData()['target']);
  }

  /**
   * Test that the actor is only included in the data once set.
   */
  public function testEventActor() : void {
    $event = new AuditLogEvent(AuditLogEntityHooks::ENTITY_CREATE, 'SUCCESS', []);
    $this->assertArrayNotHasKey('actor', $event->getData());

    $actor = ['role' => 'USER', 'user_id' => '123'];
    $event->actor = $actor;
    $this->assertSame($actor, $event->getData()['actor']);
  }

}
