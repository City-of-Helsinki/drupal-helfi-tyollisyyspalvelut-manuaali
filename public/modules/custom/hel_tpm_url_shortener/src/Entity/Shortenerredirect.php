<?php

namespace Drupal\hel_tpm_url_shortener\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hel_tpm_url_shortener\ShortenerredirectInterface;
use Drupal\link\LinkItemInterface;

/**
 * Defines the shortenerredirect entity class.
 *
 * @ContentEntityType(
 *   id = "shortenerredirect",
 *   label = @Translation("Shortener Redirect"),
 *   label_collection = @Translation("Shortener Redirects"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\hel_tpm_url_shortener\ShortenerredirectListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\hel_tpm_url_shortener\Form\ShortenerredirectForm",
 *       "edit" = "Drupal\hel_tpm_url_shortener\Form\ShortenerredirectForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "shortenerredirect",
 *   admin_permission = "access shortenerredirect overview",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/content/shortenerredirect/add",
 *     "canonical" = "/shortenerredirect/{shortenerredirect}",
 *     "edit-form" = "/admin/content/shortenerredirect/{shortenerredirect}/edit",
 *     "delete-form" = "/admin/content/shortenerredirect/{shortenerredirect}/delete",
 *     "collection" = "/admin/content/shortenerredirect"
 *   },
 * )
 */
class Shortenerredirect extends ContentEntityBase implements ShortenerredirectInterface {

  use EntityChangedTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * Short url getter.
   *
   * @return string
   *   Shortened link uri.
   */
  public function getShortUrl() {
    return \Drupal::request()->getSchemeAndHttpHost() . $this->shortened_link->uri;
  }

  /**
   * Redirect source getter.
   *
   * @return mixed
   *   Path to redirection source.
   */
  public function getRedirectSource() {
    return $this->redirect_source->path;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['hash'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Hash'))
      ->setSetting('max_length', 64)
      ->setDescription(t('The redirect hash.'));

    $fields['redirect_source'] = BaseFieldDefinition::create('redirect_source')
      ->setLabel(t('From'))
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'redirect_link',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['shortened_link'] = BaseFieldDefinition::create('link')
      ->setLabel(t('To'))
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setSettings([
        'link_type' => LinkItemInterface::LINK_GENERIC,
        'title' => DRUPAL_DISABLED,
      ])
      ->setDisplayOptions('form', [
        'type' => 'link',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the shortenerredirect was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the shortenerredirect was last edited.'));

    return $fields;
  }

}
