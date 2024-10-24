<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_stats\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\hel_tpm_service_stats\Plugin\Field\ServiceRowGroupField;
use Drupal\hel_tpm_service_stats\ServicePublishedRowInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the service published row entity class.
 *
 * @ContentEntityType(
 *   id = "service_published_row",
 *   label = @Translation("Service published row"),
 *   label_collection = @Translation("Service published rows"),
 *   label_singular = @Translation("service published row"),
 *   label_plural = @Translation("service published rows"),
 *   label_count = @PluralTranslation(
 *     singular = "@count service published rows",
 *     plural = "@count service published rows",
 *   ),
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   base_table = "service_published_row",
 *   admin_permission = "administer service_published_row",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   }
 * )
 */
final class ServicePublishedRow extends ContentEntityBase implements ServicePublishedRowInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the service published row was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the service published row was last edited.'));

    $fields['nid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Node ID'))
      ->setSetting('target_type', 'node')
      ->setDescription(t('The ID of the service published.'));

    $fields['langcode'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Language code'));

    $fields['publish_vid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Published vid'))
      ->setDescription(t('VID of published revision'));

    $fields['publish_date'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Publish date'))
      ->setDescription(t('Publish date'));

    $fields['previous_vid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Previous state vid'));

    $fields['previous_date'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Previous state date'));

    $fields['previous_state'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Previous state'));

    $fields['publish_interval'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Days between changed states'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\hel_tpm_service_stats\Plugin\Field\PublishIntervalField')
      ->setDisplayConfigurable('view', TRUE)
      ->setReadOnly(TRUE);

    $fields['group'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Group'))
      ->setSetting('target_type', 'group')
      ->setComputed(TRUE)
      ->setClass(ServiceRowGroupField::class)
      ->setReadOnly(TRUE);

    return $fields;
  }

  /**
   * Getter for publish_date.
   *
   * @return string
   *   Publish date timestamp.
   */
  public function getPublishDate(): string {
    return $this->publish_date->value;
  }

  /**
   * Getter for previous_date.
   *
   * @return string
   *   Previous date timestamp.
   */
  public function getPreviousDate(): string {
    return $this->previous_date->value;
  }

  public function getPublishVid(): int {
    return (int) $this->publish_vid->value;
  }
}
