<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_audit_log\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Site\Settings;
use Drupal\diff\DiffEntityComparison;
use Drupal\hel_tpm_audit_log\AuditLog\AuditLogEntityType;
use Drupal\hel_tpm_audit_log\AuditLog\AuditLogServiceInterface;
use Drupal\hel_tpm_audit_log\AuditLog\Event\AuditLogEvent;
use ResilientLogger\Utils\HumanReadableDiffer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Audit log entity hooks.
 */
final class AuditLogEntityHooks {

  /**
   * The entity was viewed.
   */
  public const ENTITY_READ = 'ENTITY_READ';

  /**
   * The entity was created.
   */
  public const ENTITY_CREATE = 'ENTITY_CREATE';

  /**
   * The entity was updated.
   */
  public const ENTITY_UPDATE = 'ENTITY_UPDATE';

  /**
   * The entity was deleted.
   */
  public const ENTITY_DELETE = 'ENTITY_DELETE';

  /**
   * The configured entity type matchers, keyed by entity type id.
   *
   * An entity type can have several matchers, e.g. one per bundle.
   *
   * @var array<string,list<\Drupal\hel_tpm_audit_log\AuditLog\AuditLogEntityType>>
   */
  private array $loggedEntityTypes = [];

  /**
   * Unchanged versions of updated composite entities (e.g. paragraphs).
   *
   * Keyed by entity type id and revision id.
   *
   * @var array<string,array<int|string,\Drupal\Core\Entity\ContentEntityInterface>>
   */
  private array $compositeOriginals = [];

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\hel_tpm_audit_log\AuditLog\AuditLogServiceInterface $auditLogService
   *   The audit log service.
   * @param \Drupal\diff\DiffEntityComparison $entityComparison
   *   The entity differ service.
   * @param array<string,array{entity_type: string, bundle?: string, operations?: array<string>}> $loggedEntityTypes
   *   The configured entity type matchers.
   */
  public function __construct(
    private readonly AuditLogServiceInterface $auditLogService,
    #[Autowire(service: 'diff.entity_comparison')] private readonly DiffEntityComparison $entityComparison,
    #[Autowire(param: 'hel_tpm_audit_log.audit_log_entity_types')] array $loggedEntityTypes = [],
  ) {
    foreach ($loggedEntityTypes as $type) {
      $entityType = AuditLogEntityType::fromArray($type);
      $this->loggedEntityTypes[$entityType->entityType][] = $entityType;
    }
  }

  /**
   * Implements hook_entity_view().
   *
   * @param array<string, mixed> $build
   *   The renderable array representing the entity content.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being viewed.
   */
  #[Hook('entity_view')]
  public function onEntityView(array &$build, EntityInterface $entity): void {
    if (!$this->isLoggable($entity, self::ENTITY_READ)) {
      return;
    }
    $this->logEvent($entity, self::ENTITY_READ);
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function onEntityInsert(EntityInterface $entity): void {
    if (!$this->isLoggable($entity, self::ENTITY_CREATE)) {
      return;
    }
    $this->logEvent($entity, self::ENTITY_CREATE);
  }

  /**
   * Generates a diff between the updated and previous entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to compare.
   *
   * @return null|array<string,string>
   *   The diff.
   */
  private function getEntityDiff(ContentEntityInterface $entity): ?array {
    if (!$previousEntity = $entity->getOriginal()) {
      return NULL;
    }
    $langcode = $entity->language()->getId();

    if ($previousEntity->hasTranslation($langcode)) {
      $previousEntity = $previousEntity->getTranslation($langcode);
    }
    $this->restoreCompositeOriginals($previousEntity);
    $differ = new HumanReadableDiffer();

    $fields = $this->entityComparison->compareRevisions($previousEntity, $entity);

    $changed = [];
    foreach ($fields as $key => $field) {
      if ($field['#data']['#left'] === $field['#data']['#right']) {
        continue;
      }
      $changed[$key] = $differ->diff($field['#data']['#left'], $field['#data']['#right']);
    }
    return $changed;
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function onEntityUpdate(EntityInterface $entity): void {
    if ($this->isComposite($entity)) {
      $this->storeCompositeOriginal($entity);
    }

    if ($this->isLoggable($entity, self::ENTITY_UPDATE)) {
      $extra = [];

      if ($entity instanceof ContentEntityInterface && $entity->getEntityType()->isRevisionable()) {
        $extra['ContentDiff'] = $this->getEntityDiff($entity) ?? '';
      }
      $this->logEvent($entity, self::ENTITY_UPDATE, $extra);
    }

    // Composite entities are saved before their host's update hook runs.
    // Forget them once a host has been handled. Only entities that can
    // reference composites count: other entities, like content moderation
    // states, are saved during the host's save before this hook runs for it.
    if (!$this->isComposite($entity) && $this->hasCompositeReferences($entity)) {
      $this->compositeOriginals = [];
    }
  }

  /**
   * Checks if the entity has entity_reference_revisions fields.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   TRUE if the entity can reference composite entities.
   */
  private function hasCompositeReferences(EntityInterface $entity): bool {
    if (!$entity instanceof ContentEntityInterface) {
      return FALSE;
    }
    foreach ($entity->getFieldDefinitions() as $definition) {
      if ($definition->getType() === 'entity_reference_revisions') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks if the entity is a composite entity, e.g. a paragraph.
   *
   * Composite entities are saved by entity_reference_revisions as part of
   * their host (parent) entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   TRUE if the entity is a composite entity.
   */
  private function isComposite(EntityInterface $entity): bool {
    return $entity instanceof ContentEntityInterface
      && (bool) $entity->getEntityType()->get('entity_revision_parent_id_field');
  }

  /**
   * Remembers the unchanged version of an updated composite entity.
   *
   * When the host is saved without a new revision, entity_reference_revisions
   * saves the paragraphs in place before the host's update hook runs. The
   * host's original then points to the already updated paragraph revisions,
   * so their previous values are only available here.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The composite entity.
   */
  private function storeCompositeOriginal(EntityInterface $entity): void {
    $original = $entity->getOriginal();

    if ($original instanceof ContentEntityInterface && $original->getRevisionId()) {
      // Keep the first version seen. A paragraph can be saved more than once
      // before its host is: e.g. inline entity form saves it in place on form
      // submit, and entity_reference_revisions saves it again as a new
      // revision with the host. The second save's original already has the
      // new values.
      $this->compositeOriginals[$entity->getEntityTypeId()][$original->getRevisionId()] ??= $original;
    }
  }

  /**
   * Replaces referenced composite entities with their unchanged versions.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The host's original entity. Its references are changed in place.
   * @param int $depth
   *   The nesting level, to guard against reference loops.
   */
  private function restoreCompositeOriginals(ContentEntityInterface $entity, int $depth = 0): void {
    if (!$this->compositeOriginals || $depth > 10) {
      return;
    }
    foreach ($entity->getFields() as $items) {
      if ($items->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
        continue;
      }
      $targetType = $items->getFieldDefinition()->getSetting('target_type');

      foreach ($items as $item) {
        $original = $this->compositeOriginals[$targetType][$item->target_revision_id] ?? NULL;

        if ($original) {
          $item->entity = $original;
        }
        if ($item->entity instanceof ContentEntityInterface) {
          $this->restoreCompositeOriginals($item->entity, $depth + 1);
        }
      }
    }
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function onEntityDelete(EntityInterface $entity): void {
    if (!$this->isLoggable($entity, self::ENTITY_DELETE)) {
      return;
    }
    $this->logEvent($entity, self::ENTITY_DELETE);
  }

  /**
   * Checks if the given entity type is loggable.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the operation was performed on.
   * @param string $operation
   *   The operation performed.
   *
   * @return bool
   *   TRUE if the entity type is loggable.
   */
  private function isLoggable(EntityInterface $entity, string $operation): bool {
    if (Settings::get('auditlog_entity_hooks_disable', FALSE)) {
      return FALSE;
    }
    foreach ($this->loggedEntityTypes[$entity->getEntityTypeId()] ?? [] as $type) {
      if ($type->isLoggable($entity, $operation)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Writes an audit event for an entity operation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the operation was performed on.
   * @param string $operation
   *   The operation performed.
   * @param array<mixed> $extra
   *   The extra data.
   */
  private function logEvent(EntityInterface $entity, string $operation, array $extra = []): void {
    $this->auditLogService->logOperation(new AuditLogEvent(
      operation: $operation,
      message: sprintf(
        '%s operation on %s entity (ID: %s)',
        $operation,
        $entity->getEntityTypeId(),
        $entity->id(),
      ),
      target: [
        'id' => $entity->id(),
        'type' => mb_strtoupper($entity->bundle()),
        'name' => (string) $entity->label(),
      ],
      extra: $extra,
      entity: $entity,
    ));
  }

}
