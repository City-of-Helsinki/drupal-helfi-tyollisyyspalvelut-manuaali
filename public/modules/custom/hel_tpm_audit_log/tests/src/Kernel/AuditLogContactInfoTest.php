<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_audit_log\Kernel;

use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\hel_tpm_audit_log\AuditLog\AuditLogServiceInterface;
use Drupal\hel_tpm_contact_info\Entity\ContactInfo;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that contact_info entity operations are audit logged.
 *
 * Uses the module's default 'hel_tpm_audit_log.audit_log_entity_types'
 * parameter, so this also covers the shipped configuration.
 */
#[Group('hel_tpm_audit_log')]
#[RunTestsInSeparateProcesses]
class AuditLogContactInfoTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'hook_post_action',
    'diff',
    'hel_tpm_contact_info',
    'hel_tpm_audit_log',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('hel_tpm_audit_log', ['helfi_audit_logs']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('contact_info');
    // Date formats for rendering the timestamp fields in the diff.
    $this->installConfig(['system']);

    // ContactInfo::save() copies this site-config field into the title.
    FieldStorageConfig::create([
      'field_name' => 'field_contact_name',
      'entity_type' => 'contact_info',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_contact_name',
      'entity_type' => 'contact_info',
      'bundle' => 'contact_info',
    ])->save();

    // The diff module only compares fields shown in a view display (or set in
    // diff.plugins). Mirror the site's default display for this field.
    EntityViewDisplay::create([
      'targetEntityType' => 'contact_info',
      'bundle' => 'contact_info',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_contact_name', ['type' => 'string'])->save();

    $this->setUpCurrentUser();
    $this->clearAuditLog();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // hook_post_action defers its hooks to shutdown functions. Run them while
    // the container still exists; otherwise they fail at process shutdown.
    $callbacks = &drupal_register_shutdown_function();
    while ($callback = array_shift($callbacks)) {
      call_user_func_array($callback['callback'], $callback['arguments']);
    }
    parent::tearDown();
  }

  /**
   * Tests that create, update and delete are logged with a content diff.
   */
  public function testContactInfoOperationsAreLogged(): void {
    $entity = ContactInfo::create(['field_contact_name' => 'Before']);
    $entity->save();

    $events = $this->getAuditEvents();
    $this->assertCount(1, $events);
    $this->assertEquals('ENTITY_CREATE', $events[0]['operation']);
    $this->assertEquals('CONTACT_INFO', $events[0]['target']['type']);
    $this->assertEquals('Before', $events[0]['target']['name']);
    $this->clearAuditLog();

    $entity = ContactInfo::load($entity->id());
    $entity->set('field_contact_name', 'After')->save();

    $events = $this->getAuditEvents();
    $this->assertCount(1, $events);
    $this->assertEquals('ENTITY_UPDATE', $events[0]['operation']);
    $diff = $events[0]['extra']['ContentDiff'][$entity->id() . ':contact_info.field_contact_name'] ?? '';
    $this->assertStringContainsString('Before', $diff);
    $this->assertStringContainsString('After', $diff);
    $this->clearAuditLog();

    $entity->delete();

    $events = $this->getAuditEvents();
    $this->assertCount(1, $events);
    $this->assertEquals('ENTITY_DELETE', $events[0]['operation']);
  }

  /**
   * Flushes queued events and empties the audit log table.
   */
  private function clearAuditLog(): void {
    $this->flushAuditLog();
    $this->container->get('database')->truncate('helfi_audit_logs')->execute();
  }

  /**
   * Writes the queued audit log events to the database.
   */
  private function flushAuditLog(): void {
    $service = $this->container->get(AuditLogServiceInterface::class);
    $this->assertInstanceOf(DestructableInterface::class, $service);
    $service->destruct();
  }

  /**
   * Reads all audit log events from the database.
   *
   * @return array<int, array<string, mixed>>
   *   The decoded audit events.
   */
  private function getAuditEvents(): array {
    $this->flushAuditLog();

    $messages = $this->container->get('database')
      ->select('helfi_audit_logs', 'al')
      ->fields('al', ['message'])
      ->orderBy('id')
      ->execute()
      ->fetchCol();

    return array_map(
      static fn (string $message) => json_decode($message, TRUE)['audit_event'],
      $messages,
    );
  }

}
