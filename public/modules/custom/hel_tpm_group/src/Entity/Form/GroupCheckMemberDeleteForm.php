<?php

namespace Drupal\hel_tpm_group\Entity\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\group\Entity\Form\GroupRelationshipDeleteForm;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides delete form for group entity with added checks for member deletion.
 *
 * If the removed group relationship refers to group member, check whether the
 * member is referenced from certain group content fields or from the same
 * fields belonging to subgroup content. Prevents deleting if the member is
 * referenced.
 */
class GroupCheckMemberDeleteForm extends GroupRelationshipDeleteForm {

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    LanguageManagerInterface $language_manager,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // When a group member is being removed, check which services the member is
    // responsible for. If those services exist, get them as links, show them at
    // the form and prevent removing the member.
    if (!empty($serviceLinks = $this->getNodeLinks())) {
      $form['description'] = [
        '#markup' => $this->t("Group member can't be removed. The member has responsibility for the following services. Edit the services first."),
      ];
      $form['referenced_services'] = [
        '#theme' => 'item_list',
        '#items' => $serviceLinks,
      ];
      unset($form['actions']['submit']);
    }

    return $form;
  }

  /**
   * Get translated nodes as links for the nodes the member is responsible for.
   *
   * @return array|null
   *   Array of nodes as links, NULL is not deleting group membership.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getNodeLinks(): ?array {
    // Check if the form is used to delete a group member.
    $groupRelationship = $this->getEntity();
    if (!$this->entity instanceof GroupMembership
      || !$groupRelationship instanceof GroupRelationshipInterface) {
      return NULL;
    }

    $nodeLinks = [];
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    foreach ($this->getReferencedGroupNodes($groupRelationship->getGroup()) as $node) {
      /** @var \Drupal\node\Entity\Node $node */
      if ($node->hasTranslation($langcode)) {
        $nodeLinks[] = $node->getTranslation($langcode)->toLink();
      }
      else {
        $nodeLinks[] = $node->toLink();
      }
    }

    return $nodeLinks;
  }

  /**
   * Get nodes from group and its subgroups which have reference to member.
   *
   * @param \Drupal\group\Entity\Group $group
   *   The group.
   *
   * @return array
   *   Array containing nodes, indexed by node ids.
   */
  protected function getReferencedGroupNodes(Group $group): array {
    $referencedNodes = [];
    foreach ($group->getRelatedEntities() as $relatedEntity) {
      if ($relatedEntity?->getEntityTypeId() === 'node' && $relatedEntity?->bundle() === 'service') {
        /** @var \Drupal\node\Entity\Node $relatedEntity */
        if (!$relatedEntity->isPublished()) {
          continue;
        }
        if ($this->checkMemberReferenced($relatedEntity->get('field_service_provider_updatee'))
          || $this->checkMemberReferenced($relatedEntity->get('field_responsible_updatee'))) {
          $referencedNodes[$relatedEntity->id()] = $relatedEntity;
        }
      }
      elseif ($relatedEntity?->getEntityTypeId() === 'group') {
        // Get nodes from subgroup.
        if (!empty($subNodes = $this->getReferencedGroupNodes($relatedEntity))) {
          $referencedNodes = $referencedNodes + $subNodes;
        }
      }
    }
    return $referencedNodes;
  }

  /**
   * Checks if group member is referenced in the given reference list field.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemList $field
   *   The field to check.
   *
   * @return bool
   *   TRUE if user is referenced, FALSE otherwise.
   */
  protected function checkMemberReferenced(EntityReferenceFieldItemList $field): bool {
    if (!$this->entity instanceof GroupMembership) {
      return FALSE;
    }
    foreach ($field->referencedEntities() as $referencedEntity) {
      if (!$referencedEntity instanceof User) {
        continue;
      }
      if ($referencedEntity->id() === $this->entity->getEntityId()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
