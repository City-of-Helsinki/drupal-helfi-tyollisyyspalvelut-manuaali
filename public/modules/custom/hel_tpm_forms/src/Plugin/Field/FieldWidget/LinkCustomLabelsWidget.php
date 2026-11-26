<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_forms\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Link widget with configurable labels and descriptions for URL and link text.
 *
 * Inherits automatic https scheme handling from AutoSchemeLinkWidget. Empty
 * settings fall back to the core link widget defaults.
 */
#[FieldWidget(
  id: 'hel_tpm_forms_link_custom_labels',
  label: new TranslatableMarkup('Link with automatic https (custom labels)'),
  field_types: ['link'],
)]
class LinkCustomLabelsWidget extends AutoSchemeLinkWidget {

  /**
   * Overridable sub-element properties keyed by setting name.
   *
   * Values are [sub-element key, render array property].
   */
  protected const OVERRIDES = [
    'uri_label' => ['uri', '#title'],
    'uri_description' => ['uri', '#description'],
    'title_label' => ['title', '#title'],
    'title_description' => ['title', '#description'],
  ];

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'uri_label' => '',
      'uri_description' => '',
      'title_label' => '',
      'title_description' => '',
      'add_more_label' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['uri_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL label'),
      '#default_value' => $this->getSetting('uri_label'),
      '#description' => $this->t('Leave empty to use the default label.'),
    ];
    $elements['uri_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('URL description'),
      '#default_value' => $this->getSetting('uri_description'),
      '#rows' => 2,
      '#description' => $this->t('Replaces the default URL help text. Leave empty to use the default help text.'),
    ];
    $elements['title_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text label'),
      '#default_value' => $this->getSetting('title_label'),
      '#description' => $this->t('Leave empty to use the default label.'),
    ];
    $elements['title_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Link text description'),
      '#default_value' => $this->getSetting('title_description'),
      '#rows' => 2,
      '#description' => $this->t('Leave empty for no description.'),
    ];
    // The add more button only exists for fields with unlimited cardinality.
    if ($this->isUnlimited()) {
      $elements['add_more_label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Add more button text'),
        '#default_value' => $this->getSetting('add_more_label'),
        '#description' => $this->t('Leave empty to use the default text.'),
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    $labels = [
      'uri_label' => $this->t('URL label'),
      'uri_description' => $this->t('URL description'),
      'title_label' => $this->t('Link text label'),
      'title_description' => $this->t('Link text description'),
    ];
    if ($this->isUnlimited()) {
      $labels['add_more_label'] = $this->t('Add more button text');
    }
    foreach ($labels as $setting => $label) {
      $value = $this->getSetting($setting);
      if (!empty($value)) {
        $summary[] = $this->t('@setting: @value', [
          '@setting' => $label,
          '@value' => mb_strimwidth($value, 0, 60, '…'),
        ]);
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    foreach (static::OVERRIDES as $setting => [$key, $property]) {
      $value = $this->getSetting($setting);
      if (!empty($value) && isset($element[$key])) {
        $element[$key][$property] = $value;
      }
    }

    // Render URL and link text descriptions below their inputs. The widget
    // specific theme wrapper suggestion lets themes that force descriptions
    // before inputs override the placement for this widget only; it falls back
    // to the regular form_element template.
    foreach (['uri', 'title'] as $key) {
      if (isset($element[$key])) {
        $element[$key]['#description_display'] = 'after';
        $element[$key]['#theme_wrappers'] = ['form_element__link_custom_labels'];
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Unlimited fields with values get no extra empty item. A new item is added
   * only with the add more button. An empty field still shows one item.
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL): array {
    $field_name = $this->fieldDefinition->getName();
    $parents = $form['#parents'];
    // Core renders items_count + 1 items for unlimited fields. Set the initial
    // count before core does, so that only the existing items are rendered.
    if ($get_delta === NULL && $this->isUnlimited() && !static::getWidgetState($parents, $field_name, $form_state)) {
      static::setWidgetState($parents, $field_name, $form_state, [
        'items_count' => max(count($items) - 1, 0),
        'array_parents' => [],
      ]);
    }
    return parent::form($items, $form, $form_state, $get_delta);
  }

  /**
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state): array {
    $elements = parent::formMultipleElements($items, $form, $form_state);

    $add_more_label = $this->getSetting('add_more_label');
    if (!empty($add_more_label) && isset($elements['add_more'])) {
      $elements['add_more']['#value'] = $add_more_label;
    }

    return $elements;
  }

  /**
   * Checks whether the field has unlimited cardinality.
   *
   * @return bool
   *   TRUE if the field accepts an unlimited number of values.
   */
  protected function isUnlimited(): bool {
    return $this->fieldDefinition->getFieldStorageDefinition()->getCardinality() === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
  }

}
