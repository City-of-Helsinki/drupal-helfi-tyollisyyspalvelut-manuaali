<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_audit_log\Kernel;

use Drupal\Core\DestructableInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\PermissionScopeInterface;
use Drupal\hel_tpm_audit_log\AuditLog\AuditLogServiceInterface;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that group and group membership operations are audit logged.
 *
 * Uses the module's default 'hel_tpm_audit_log.audit_log_entity_types'
 * parameter, which lists several group_content bundles.
 */
#[Group('hel_tpm_audit_log')]
#[RunTestsInSeparateProcesses]
class AuditLogGroupTest extends GroupKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'diff',
    'hel_tpm_audit_log',
  ];

  /**
   * The group the tests operate on.
   */
  private GroupInterface $group;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('hel_tpm_audit_log', ['helfi_audit_logs']);

    // Same id as on the site, so the membership bundle is
    // 'organisation-group_membership'.
    $this->createGroupType([
      'id' => 'organisation',
      'creator_membership' => FALSE,
    ]);
    $this->createGroupRole([
      'id' => 'organisation-editor',
      'group_type' => 'organisation',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
    ]);
    $this->createGroupRole([
      'id' => 'organisation-admin',
      'group_type' => 'organisation',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
    ]);
    // Drop the user events from the parent setUp().
    $this->clearAuditLog();

    $this->group = $this->createGroup([
      'type' => 'organisation',
      'label' => 'Test organisation',
    ]);
  }

  /**
   * Tests that group create, update and delete are logged.
   */
  public function testGroupOperationsAreLogged(): void {
    $events = $this->getAuditEvents();
    $this->assertCount(1, $events);
    $this->assertEquals('ENTITY_CREATE', $events[0]['operation']);
    $this->assertEquals('ORGANISATION', $events[0]['target']['type']);
    $this->assertEquals('Test organisation', $events[0]['target']['name']);
    $this->clearAuditLog();

    $this->group->set('label', 'Renamed organisation')->save();
    $events = $this->getAuditEvents();
    $this->assertCount(1, $events);
    $this->assertEquals('ENTITY_UPDATE', $events[0]['operation']);
    $this->assertEquals('Renamed organisation', $events[0]['target']['name']);
    $this->clearAuditLog();

    $this->group->delete();
    $operations = array_column($this->getAuditEvents(), 'operation');
    $this->assertContains('ENTITY_DELETE', $operations);
  }

  /**
   * Tests that membership changes are logged with group and role details.
   */
  public function testMembershipOperationsAreLogged(): void {
    $account = $this->createUser([], 'member-user');
    $this->clearAuditLog();

    // Add a member.
    $this->group->addMember($account, ['group_roles' => ['organisation-editor']]);
    $events = $this->getMembershipEvents();
    $this->assertCount(1, $events);
    $this->assertEquals('ENTITY_CREATE', $events[0]['operation']);
    $this->assertEquals('ORGANISATION-GROUP_MEMBERSHIP', $events[0]['target']['type']);
    $this->assertEquals([
      'id' => $this->group->id(),
      'type' => 'organisation',
      'name' => 'Test organisation',
    ], $events[0]['extra']['group']);
    $this->assertEquals($account->id(), $events[0]['extra']['member']['id']);
    $this->assertEquals('member-user', $events[0]['extra']['member']['name']);
    $this->assertEquals(['organisation-editor'], $events[0]['extra']['group_roles']);
    $this->assertArrayNotHasKey('group_roles_before', $events[0]['extra']);
    $this->clearAuditLog();

    // Change the member's roles.
    $relationship = $this->group->getMember($account)->getGroupRelationship();
    $relationship->set('group_roles', ['organisation-admin', 'organisation-editor'])->save();
    $events = $this->getMembershipEvents();
    $this->assertCount(1, $events);
    $this->assertEquals('ENTITY_UPDATE', $events[0]['operation']);
    $this->assertEquals(['organisation-admin', 'organisation-editor'], $events[0]['extra']['group_roles']);
    $this->assertEquals(['organisation-editor'], $events[0]['extra']['group_roles_before']);
    $this->clearAuditLog();

    // Remove the member.
    $this->group->removeMember($account);
    $events = $this->getMembershipEvents();
    $this->assertCount(1, $events);
    $this->assertEquals('ENTITY_DELETE', $events[0]['operation']);
    $this->assertEquals($account->id(), $events[0]['extra']['member']['id']);
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
   * Reads the group membership audit log events.
   *
   * Group also saves the member's user account on membership changes, which
   * is logged as a separate user event.
   *
   * @return list<array<string, mixed>>
   *   The decoded membership audit events.
   */
  private function getMembershipEvents(): array {
    return array_values(array_filter(
      $this->getAuditEvents(),
      static fn (array $event) => $event['target']['type'] === 'ORGANISATION-GROUP_MEMBERSHIP',
    ));
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
