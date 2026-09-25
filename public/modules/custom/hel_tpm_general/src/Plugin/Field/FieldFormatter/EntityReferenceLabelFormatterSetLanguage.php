<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'entity reference label language' formatter.
 *
 * There is a use case to always show the label of the referenced entities in
 * a specific language, even if there is a translation available. This formatter
 * allows selecting the language for the label shown for all translations.
 */
#[FieldFormatter(
  id: 'hel_tpm_general_entity_reference_label_language',
  label: new TranslatableMarkup('Label in selected language'),
  description: new TranslatableMarkup('Display the label of the referenced entities in selected language.'),
  field_types: [
    'entity_reference',
  ],
)]
final class EntityReferenceLabelFormatterSetLanguage extends EntityReferenceLabelFormatter {

  /**
   * Constructs an EntityReferenceLabelFormatterSetLanguage object.
   *
   * @param string $plugin_id
   *   The plugin ID for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language manager interface.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, private readonly LanguageManagerInterface $languageManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
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
  public static function defaultSettings() {
    return [
      'langcode' => 'fi',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $options = [];
    foreach ($this->languageManager->getLanguages() as $language) {
      $options[$language->getId()] = $language->getName();
    }

    $elements['langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#description' => $this->t('The label of the referenced entities is always displayed in this language.'),
      '#default_value' => $this->getSetting('langcode'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $langcode = $this->getSetting('langcode');
    $language = $this->languageManager->getLanguage($langcode);
    $summary[] = $this->t('Language: @language', ['@language' => $language ? $language->getName() : $langcode]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    return parent::getEntitiesToView($items, $this->getSetting('langcode'));
  }

}
