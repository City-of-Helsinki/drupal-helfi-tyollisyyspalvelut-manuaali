<?php

namespace Drupal\service_manual_workflow;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * {@inheritdoc}
 */
class ViewsData {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The moderation information.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * Creates a new ViewsData instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModerationInformationInterface $moderation_information) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationInformation = $moderation_information;
  }

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = [];

    $entity_types_with_moderation = array_filter($this->entityTypeManager->getDefinitions(), function (EntityTypeInterface $type) {
      return $this->moderationInformation->isModeratedEntityType($type);
    });

    foreach ($entity_types_with_moderation as $entity_type) {
      $revision_table = $entity_type->getRevisionDataTable() ?: $entity_type->getRevisionTable();
      $data[$revision_table]['moderation_state'] = [
        'title' => t('Latest Moderation state'),
        'join' => [],
        'field' => [
          'id' => 'latest_moderation_state_field',
          'default_formatter' => 'content_moderation_state',
          'field_name' => 'moderation_state',
        ],
        'filter' => [
          'id' => 'latest_moderation_state_filter'
        ],
        'sort' => ['id' => 'moderation_state_sort'],
      ];
    }

    return $data;
  }

}
