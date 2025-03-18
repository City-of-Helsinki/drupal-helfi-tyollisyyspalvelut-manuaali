<?php

namespace Drupal\hel_tpm_general\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Plugin implementation of the 'Language link to content' formatter.
 *
 * @FieldFormatter(
 *   id = "hel_tpm_general_language_link_to_content",
 *   label = @Translation("Language link to content"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class LanguageLinkToContentFormatter extends FormatterBase {

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    LanguageManagerInterface $language_manager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#markup' => $this->createLink($item),
      ];
    }

    return $element;
  }

  /**
   * Create link for language versions.
   *
   * If entity is not translatable provide link to entity
   * which includes current langcode.
   *
   * @param \Drupal\Core\Field\Plugin\Field\FieldType\StringItem $item
   *   String item.
   *
   * @return \Drupal\Core\GeneratedLink
   *   Link to language version.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function createLink(StringItem $item) {
    $entity = $item->getEntity();
    $options = [];
    if (!$entity->isTranslatable()) {
      $options = ['language' => $this->languageManager->getCurrentLanguage()];
    }
    return $entity->toLink($item->value, 'canonical', $options)->toString();
  }

}
