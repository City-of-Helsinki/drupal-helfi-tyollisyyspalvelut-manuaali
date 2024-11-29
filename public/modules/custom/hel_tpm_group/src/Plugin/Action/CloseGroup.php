<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group\Plugin\Action;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Drupal\node\NodeInterface;
use Elastica\Exception\Bulk\Response\ActionException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Close Group action.
 */
#[Action(
  id: 'hel_tpm_group_close_group',
  label: new TranslatableMarkup('Close group'),
  type: 'group'
)]
class CloseGroup extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly GroupRelationTypeManagerInterface $groupRelationTypeManager,
    private readonly ModerationInformationInterface $moderationInformation
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('group_relation_type.manager'),
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#markup' => $this->t('
       You are about to close selected group. 
       This will remove all users from group, archive all group content and remove references to responsibility services.'
      )
    ];
    return $form;
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function access($entity, AccountInterface $account = NULL, $return_as_object = FALSE): AccessResultInterface|bool {
    $access = AccessResult::forbidden();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    if ($this->canCloseGroup($entity)) {
      $access = AccessResult::allowed();
    }

    if ($access->isAllowed()) {
      $access = $entity->access('delete', $account, TRUE);
    }

    return $return_as_object ? $access : $access->isAllowed();
  }


  /**
   * {@inheritdoc}
   */
  public function execute(GroupInterface $group = NULL): void {
    if (!$this->canCloseGroup($group)) {
      return;
    }
    // Archive all group content entities.
    $this->archiveGroupContentEntities($group);
    // Remove users from group.
    $this->removeUsersFromGroup($group);
    // Remove references from services.
    $this->removeReferencesToGroup($group);

    // Unpublish group.
    $group->setUnpublished();
    $group->save();
    $this->messenger()->addStatus(new TranslatableMarkup('Succesfully archived @group', ['@group' => $group->label()]));
  }

  /**
   * Validate group can be closed.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *  Group entity interface.
   *
   * @return bool
   *   bool.
   */
  protected function canCloseGroup(GroupInterface $group): bool {
    $subgroups = $this->getGroupContent($group, 'group');
    if (empty($subgroups)) {
      return TRUE;
    }

    // Check if there's published subgroups.
    foreach ($subgroups as $key => $subgroup) {
      if (!$subgroup->isPublished()) {
        unset($subgroups[$key]);
      }
    }

    // Throw error if there's published subgroups.
    if (!empty($subgroups)) {
      $this->messenger()->addError(new TranslatableMarkup(
        'Skipped @group because it has subgroup(s)', ['@group' => $group->label()]
      ));
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Remove all users from archived group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *  Group interface.
   *
   * @return void
   *  -
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function removeUsersFromGroup(GroupInterface $group): void {
    $memberships = $group->getMembers();
    if (empty($memberships)) {
      return;
    }
    foreach ($memberships as $membership) {
      $membership->getGroupRelationship()->delete();
    }
  }

  /**
   * Archive moderated group content.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *  Group interface.
   *
   * @return void
   *  -
   */
  protected function archiveGroupContentEntities(GroupInterface $group): void {
    $content = $this->getGroupContent($group, 'node');

    if (empty($content)) {
      return;
    }

    foreach ($content as $node) {
      if ($node->isTranslatable()) {
        $translation_languages = $node->getTranslationLanguages();
        foreach ($translation_languages as $language) {
          if (!$node->hasTranslation($language->getId())) {
            continue;
          }
          $translation = $node->getTranslation($language->getId());
          $this->archiveContent($translation);
        }
      }
      else {
        $this->archiveContent($node);
      }
    }
  }

  protected function archiveContent(NodeInterface $node): void {
    $node->setUnpublished();
    if ($this->moderationInformation->isModeratedEntity($node)) {
      $node->set('moderation_state', 'archived');
    }
    $node->save();
  }

  /**
   * Remove all responsibility references for group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *  Group interface.
   *
   * @return void
   *  -
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function removeReferencesToGroup(GroupInterface $group): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery();
    $or = $query->orConditionGroup()
      ->condition('field_responsible_municipality', $group->id())
      ->condition('field_service_producer', $group->id());
    $query->condition($or);
    $query->accessCheck(FALSE);
    $nids = $query->execute();

    if (empty($nids)) {
      return;
    }

    $fields = [
      'field_responsible_municipality',
      'field_service_producer'
    ];

    foreach ($nids as $nid) {
      $node = $storage->load($nid);
      foreach ($fields as $field) {
        $this->removeFieldReference($node, $field, $group->id());
      }
      $node->save();
    }
  }

  /**
   * Remove field reference from node field.
   *
   * @param \Drupal\node\NodeInterface $node
   *  Node interface.
   * @param string $field
   *  Field name.
   * @param int $entity_id
   *  Id of the entity which reference we want to remove.
   *
   * @return void
   *  -
   */
  protected function removeFieldReference(NodeInterface &$node, string $field, $entity_id) {
    $value = $node->get($field)->getValue();
    if (empty($value)) {
      return;
    }
    foreach ($value as $key => $val) {
      if ($val['target_id'] == $entity_id) {
        unset($value[$key]);
      }
      $node->set($field, $value);
    }
  }

  /**
   * Get group content entities.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *  Group interface.
   * @param string $entity_type
   *  Entity type parameter.
   *
   * @return array
   *   Array of content entities.
   */
  protected function getGroupContent(GroupInterface $group, string $entity_type): array {
    $content = [];
    $plugin_ids = $this->groupRelationTypeManager->getPluginIdsByEntityTypeId($entity_type);
    foreach ($plugin_ids as $plugin_id) {
      $content = array_merge($content, $group->getRelatedEntities($plugin_id));
    }
    return $content;
  }
}
