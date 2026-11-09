<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_forms\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\LanguageSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Language widget rendering the languages as radio buttons.
 */
#[FieldWidget(
  id: 'hel_tpm_forms_language_radios',
  label: new TranslatableMarkup('Language radio buttons'),
  field_types: ['language'],
)]
class LanguageRadiosWidget extends LanguageSelectWidget implements ContainerFactoryPluginInterface {

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected readonly LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
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
      $configuration['third_party_settings'],
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $options = $this->getLanguageOptions();
    // A radio button must always be selected, as the language can't be empty.
    $default_value = $items[$delta]->value;
    if (!isset($options[$default_value])) {
      $default_value = $this->languageManager->getDefaultLanguage()->getId();
    }

    $element['value'] = $element + [
      '#type' => 'radios',
      '#options' => $options,
      '#default_value' => $default_value,
    ];
    // The language can't be empty, so a language must always be selected.
    $element['value']['#required'] = TRUE;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'option_labels' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $labels = $this->getSetting('option_labels');
    $element['option_labels'] = [
      '#type' => 'details',
      '#title' => $this->t('Option labels'),
      '#description' => $this->t('Labels of the language options. The name of the language is used when empty. The labels are translated with configuration translation.'),
    ];
    foreach ($this->getLanguages() as $langcode => $language) {
      $element['option_labels'][$langcode] = [
        '#type' => 'textfield',
        '#title' => $language->getName(),
        '#default_value' => $labels[$langcode] ?? '',
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $labels = array_filter($this->getSetting('option_labels'));
    if ($labels) {
      $summary[] = $this->t('Option labels: @labels', ['@labels' => implode(', ', $labels)]);
    }
    return $summary;
  }

  /**
   * Gets the selectable languages.
   *
   * @return array
   *   Option labels keyed by language code. The configured option label, or
   *   the language name same as in the language select list of the language
   *   module.
   */
  protected function getLanguageOptions(): array {
    $labels = $this->getSetting('option_labels');
    $options = [];
    foreach ($this->getLanguages() as $langcode => $language) {
      if (!empty($labels[$langcode])) {
        $options[$langcode] = $labels[$langcode];
      }
      elseif ($language->isLocked()) {
        $options[$langcode] = $this->t('- @name -', ['@name' => $language->getName()]);
      }
      else {
        $options[$langcode] = $language->getName();
      }
    }
    return $options;
  }

  /**
   * Gets the languages selectable with the widget.
   *
   * @return \Drupal\Core\Language\LanguageInterface[]
   *   The languages keyed by language code.
   */
  protected function getLanguages(): array {
    $state = $this->getSetting('include_locked') ? LanguageInterface::STATE_ALL : LanguageInterface::STATE_CONFIGURABLE;
    return $this->languageManager->getLanguages($state);
  }

}
