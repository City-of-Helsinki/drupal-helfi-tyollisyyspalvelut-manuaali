<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_audit_log\AuditLog\EventSubscriber;

use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\hel_tpm_audit_log\AuditLog\Event\AuditLogEvent;
use Drupal\hel_tpm_audit_log\Hook\AuditLogEntityHooks;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds group, member and group role details to group membership events.
 *
 * Group relationships are not revisionable, so the entity hooks add no
 * content diff for them. This records the group roles instead, and the
 * previous roles when an update changed them.
 *
 * Does nothing when the group module is not enabled.
 */
readonly class AuditLogGroupMembershipSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AuditLogEvent::class => ['addMembershipDetails'],
    ];
  }

  /**
   * Adds the membership details to the event.
   *
   * @param \Drupal\hel_tpm_audit_log\AuditLog\Event\AuditLogEvent $event
   *   The audit log event.
   */
  public function addMembershipDetails(AuditLogEvent $event): void {
    $relationship = $event->entity;

    if (!$relationship instanceof GroupRelationshipInterface || $relationship->getPluginId() !== 'group_membership') {
      return;
    }
    $group = $relationship->getGroup();
    $member = $relationship->get('entity_id')->entity;

    $event->extra['group'] = [
      'id' => $relationship->getGroupId(),
      'type' => $relationship->getGroupTypeId(),
      'name' => $group ? (string) $group->label() : NULL,
    ];
    $event->extra['member'] = [
      'id' => $relationship->get('entity_id')->target_id,
      'name' => $member ? (string) $member->label() : NULL,
    ];
    $roles = $this->getRoleIds($relationship);
    $event->extra['group_roles'] = $roles;

    $original = $relationship->getOriginal();
    if ($event->operation === AuditLogEntityHooks::ENTITY_UPDATE && $original instanceof GroupRelationshipInterface) {
      $previousRoles = $this->getRoleIds($original);

      if ($previousRoles !== $roles) {
        $event->extra['group_roles_before'] = $previousRoles;
      }
    }
  }

  /**
   * Gets the sorted group role ids of a membership.
   *
   * @param \Drupal\group\Entity\GroupRelationshipInterface $relationship
   *   The membership relationship.
   *
   * @return list<string>
   *   The group role ids.
   */
  private function getRoleIds(GroupRelationshipInterface $relationship): array {
    if (!$relationship->hasField('group_roles')) {
      return [];
    }
    $roles = array_column($relationship->get('group_roles')->getValue(), 'target_id');
    sort($roles);

    return $roles;
  }

}
