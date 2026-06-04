<?php

declare(strict_types=1);

namespace Drupal\service_manual_workflow\Plugin\Validation\Constraint;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\require_on_publish\Plugin\Validation\Constraint\RequireOnPublishValidator;
use Symfony\Component\Validator\Constraint;

/**
 * Validates the Service Require on Publish constraint.
 */
final class ServiceRequireOnPublishConstraintValidator extends RequireOnPublishValidator {

  /**
   * Holds a list of additional states indicating readiness for publishing.
   */
  protected const ADDITIONAL_PUBLISHED_STATE_IDS = [
    'ready_to_publish',
  ];

  /**
   * Service fields that are not required in ready to publish state.
   */
  protected const SERVICE_FIELDS_NOT_REQUIRED_ON_READY_TO_PUBLISH = [
    'field_service_req_speacialist',
    'field_service_coordination',
    'field_obligatoryness_freetext',
    'field_obligatoryness',
    'field_career_markings',
    'field_statements_unemployment',
    'field_statements',
    'field_service_suits_job_search',
    'field_responsible_updatee',
  ];

  /**
   * Determines the published status of the given entity.
   *
   * If the entity is a paragraph entity, its published status is determined
   * based on the parent entity. Otherwise, the published status is determined
   * directly from the given entity.
   *
   * @param mixed $entity
   *   The entity whose published status needs to be determined.
   *
   * @return bool
   *   TRUE if the entity is published, FALSE otherwise.
   */
  protected function determinePublishedStatus($entity): bool {
    if ($this->isParagraphEntity($entity) && ($parent = $entity->getParentEntity())) {
      return $this->determineParagraphPublishedStatus($parent);
    }

    return $this->determineEntityPublishedStatus($entity);
  }

  /**
   * Validates a scalar/text/boolean/etc. field with ROP settings.
   */
  protected function validateField(FieldItemListInterface $items, bool $is_published, Constraint $constraint): void {
    if ($is_published && $this->isServiceReadyToPublishFieldExempt($items)) {
      return;
    }

    parent::validateField($items, $is_published, $constraint);
  }

  /**
   * Checks whether a service field is exempt in ready to publish state.
   */
  protected function isServiceReadyToPublishFieldExempt(FieldItemListInterface $items): bool {
    $entity = $items->getEntity();

    if (
      $entity->getEntityTypeId() !== 'node'
      || $entity->bundle() !== 'service'
      || !$entity->hasField('moderation_state')
      || $entity->get('moderation_state')->isEmpty()
      || in_array($entity->get('moderation_state')->value, self::ADDITIONAL_PUBLISHED_STATE_IDS, TRUE)
    ) {
      return FALSE;
    }

    $field_config = $items->getFieldDefinition();

    return $field_config instanceof FieldConfigInterface
      && in_array($items->getName(), self::SERVICE_FIELDS_NOT_REQUIRED_ON_READY_TO_PUBLISH, TRUE);
  }

  /**
   * Determines published status from workflow state when available.
   */
  protected function determineEntityPublishedStatus(ContentEntityInterface $entity): bool {
    if (
      $this->moderationInfo
      && $this->moderationInfo->isModeratedEntity($entity)
      && $entity->hasField('moderation_state')
      && !$entity->get('moderation_state')->isEmpty()
    ) {
      $workflow = $this->moderationInfo->getWorkflowForEntity($entity);
      $state_id = $entity->get('moderation_state')->value;
      $state = $workflow?->getTypePlugin()->getState($state_id);

      if ($state) {
        return $this->isStatePublished($state);
      }
    }

    return $entity->isPublished();
  }

  /**
   * Determines published status for a paragraph based on its parent entity.
   */
  protected function determineParagraphPublishedStatus(ContentEntityInterface $parent): bool {
    if (
      $this->moderationInfo
      && $this->moderationInfo->isModeratedEntity($parent)
      && ($state_id = $this->getModerationStateFromRequest())
    ) {
      $workflow = $this->moderationInfo->getWorkflowForEntity($parent);
      $state = $workflow?->getTypePlugin()->getState($state_id);

      if ($state) {
        return $this->isStatePublished($state);
      }
    }

    $posted_status = $this->getStatusFromRequest();

    if ($posted_status !== NULL) {
      return $posted_status;
    }

    return $parent->isPublished();
  }

  /**
   * Checks whether the entity is a paragraph entity.
   */
  protected function isParagraphEntity(EntityInterface $entity): bool {
    return $this->moduleHandler->moduleExists('paragraphs')
      && $entity->getEntityTypeId() === 'paragraph';
  }

  /**
   * Checks whether a workflow state should be treated as published.
   */
  protected function isStatePublished($state): bool {
    return $state->isPublishedState()
      || in_array($state->id(), self::ADDITIONAL_PUBLISHED_STATE_IDS, TRUE);
  }

  /**
   * Gets the posted moderation state ID, if available.
   */
  protected function getModerationStateFromRequest(): ?string {
    $requestStack = $this->requestStack;

    if (!$requestStack->isMethod('POST')) {
      return NULL;
    }

    $moderation_state = $requestStack->get('moderation_state');

    if (!is_array($moderation_state) || !isset($moderation_state[0]['state'])) {
      return NULL;
    }

    return (string) $moderation_state[0]['state'];
  }

  /**
   * Gets the posted publication status, if available.
   */
  protected function getStatusFromRequest(): ?bool {
    $requestStack = $this->requestStack;

    if (!$requestStack->isMethod('POST')) {
      return NULL;
    }

    $status = $requestStack->get('status');

    if (!is_array($status) || !array_key_exists('value', $status)) {
      return NULL;
    }

    return (bool) $status['value'];
  }

}
