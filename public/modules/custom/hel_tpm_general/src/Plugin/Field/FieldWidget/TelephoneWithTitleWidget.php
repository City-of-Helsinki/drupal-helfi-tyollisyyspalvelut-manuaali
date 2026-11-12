<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hel_tpm_general\PhoneNumberHandler;
use Drupal\hel_tpm_general\Plugin\Field\FieldType\TelephoneWithTitleItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'Telephone with title' widget.
 *
 * Shows the title and the telephone number side by side, like the link widget.
 */
#[FieldWidget(
  id: 'hel_tpm_telephone_with_title_default',
  label: new TranslatableMarkup('Telephone with title'),
  field_types: ['hel_tpm_telephone_with_title'],
)]
class TelephoneWithTitleWidget extends WidgetBase implements TrustedCallbackInterface {

  /**
   * The phone number handler.
   */
  protected PhoneNumberHandler $phoneNumberHandler;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'placeholder_title' => '',
      'placeholder_value' => '',
      'country_code' => '+358',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->phoneNumberHandler = $container->get('hel_tpm_general.phone_number_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $elements['placeholder_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder for title'),
      '#default_value' => $this->getSetting('placeholder_title'),
    ];
    $elements['placeholder_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder for telephone number'),
      '#default_value' => $this->getSetting('placeholder_value'),
    ];
    $elements['country_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country code'),
      '#description' => $this->t('The default country code, e.g. +358. It is shown in its own input in front of the telephone number, where it can be changed, and is saved with the number. A leading zero typed into the number is removed. Leave empty to save numbers as entered.'),
      '#default_value' => $this->getSetting('country_code'),
      '#size' => 6,
      '#maxlength' => 4,
      '#pattern' => '\+[0-9]{1,3}',
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    if ($this->getSetting('placeholder_title') !== '') {
      $summary[] = $this->t('Placeholder for title: @placeholder', [
        '@placeholder' => $this->getSetting('placeholder_title'),
      ]);
    }
    if ($this->getSetting('placeholder_value') !== '') {
      $summary[] = $this->t('Placeholder for telephone number: @placeholder', [
        '@placeholder' => $this->getSetting('placeholder_value'),
      ]);
    }
    if (empty($summary)) {
      $summary[] = $this->t('No placeholders');
    }
    if ($this->getSetting('country_code') !== '') {
      $summary[] = $this->t('Country code: @code', ['@code' => $this->getSetting('country_code')]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    /** @var \Drupal\hel_tpm_general\Plugin\Field\FieldType\TelephoneWithTitleItem $item */
    $item = $items[$delta];
    $title_required = (bool) $this->getFieldSetting('title_required');

    $element['title'] = [
      '#type' => 'textfield',
      '#title' => $item->getTitleLabel(),
      '#description' => FieldFilteredMarkup::create((string) $item->getTitleDescription()),
      '#description_display' => 'after',
      // The front-end theme shows descriptions before inputs by default.
      '#description_display_override' => 'after',
      '#default_value' => $item->title ?? NULL,
      '#maxlength' => TelephoneWithTitleItem::TITLE_MAX_LENGTH,
      '#placeholder' => $this->getSetting('placeholder_title'),
      '#required' => $element['#required'] && $title_required,
      // The number comes first, like the URL in the link widget.
      '#weight' => 1,
    ];
    $element['value'] = [
      '#type' => 'tel',
      '#title' => $item->getValueLabel(),
      '#default_value' => $item->value ?? NULL,
      '#maxlength' => TelephoneWithTitleItem::NUMBER_MAX_LENGTH,
      '#placeholder' => $this->getSetting('placeholder_value'),
      '#description' => FieldFilteredMarkup::create((string) $item->getValueDescription()),
      '#description_display' => 'after',
      '#description_display_override' => 'after',
      '#required' => $element['#required'],
      '#weight' => 0,
    ];

    // The country code has its own input in front of the number input. It
    // defaults to the widget setting and can be changed, e.g. for foreign
    // numbers. On save it is joined with the number, e.g. "+358 40 123 4567".
    $default_country_code = (string) $this->getSetting('country_code');
    if ($default_country_code !== '') {
      [$country_code, $national] = $this->phoneNumberHandler->splitNumber((string) ($item->value ?? ''), $default_country_code);

      // Rendered inside the number input, see preRenderCountryCode(). It has
      // no form element wrapper there, so the label is given as aria-label.
      $element['country_code'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Country code'),
        '#default_value' => $country_code,
        '#size' => 5,
        '#maxlength' => 5,
        '#theme_wrappers' => [],
        '#attributes' => [
          'class' => ['telephone-with-title__country-code-input'],
          'aria-label' => $this->t('Country code'),
          'inputmode' => 'tel',
          'autocomplete' => 'tel-country-code',
          'pattern' => '\+[0-9]{1,4}',
        ],
        '#element_validate' => [[static::class, 'validateCountryCode']],
      ];
      $element['#pre_render'][] = [static::class, 'preRenderCountryCode'];
      $element['value']['#default_value'] = $national;
      $element['value']['#wrapper_attributes']['class'][] = 'telephone-with-title__number--country-code';
      $element['value']['#attributes']['data-telephone-country-code'] = 'number';
      $element['value']['#attached']['library'][] = 'hel_tpm_general/telephone_country_code';
      if ((string) $this->getFieldSetting('value_description') === '') {
        $element['value']['#description'] = $this->t('Enter the number without the country code, e.g. 40 123 4567.');
      }
    }

    // Single value fields are wrapped in a fieldset, so that the field label
    // is shown, like in the link widget.
    if ($this->fieldDefinition->getFieldStorageDefinition()->getCardinality() === 1) {
      $element += ['#type' => 'fieldset'];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['preRenderCountryCode'];
  }

  /**
   * Pre-render callback: renders the country code inside the number input.
   *
   * The country code input is built as its own form element, so that the Form
   * API handles its value. Before rendering it is moved into the field prefix
   * of the number input, where CSS places it on top of the number input.
   *
   * @param array $element
   *   The widget element of one item.
   *
   * @return array
   *   The altered element.
   */
  public static function preRenderCountryCode(array $element): array {
    if (isset($element['country_code'], $element['value'])) {
      $element['value']['#field_prefix'] = $element['country_code'];
      unset($element['country_code']);
    }
    return $element;
  }

  /**
   * Element validate callback for the country code input.
   *
   * @param array $element
   *   The country code element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateCountryCode(array $element, FormStateInterface $form_state): void {
    $value = trim((string) $element['#value']);
    if ($value !== '' && !preg_match(PhoneNumberHandler::COUNTRY_CODE_PATTERN, $value)) {
      $form_state->setError($element, t('%name must be a plus sign followed by 1–4 digits, e.g. +358.', [
        '%name' => $element['#title'],
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state): array {
    $elements = parent::formMultipleElements($items, $form, $form_state);
    // The button is identified by its #name, so changing the label is safe.
    $add_more_label = (string) $this->getFieldSetting('add_more_label');
    if ($add_more_label !== '' && isset($elements['add_more'])) {
      $elements['add_more']['#value'] = $add_more_label;
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state): array|bool {
    // Show the error on the sub-element (title or number) it belongs to. The
    // property path is relative to the field, e.g. "0.title", like in core's
    // LinkWidget::errorElement().
    $property = explode('.', $error->getPropertyPath())[1] ?? NULL;
    if ($property !== NULL && isset($element[$property])) {
      return $element[$property];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $default_country_code = (string) $this->getSetting('country_code');
    foreach ($values as &$value) {
      $value['title'] = trim((string) ($value['title'] ?? ''));
      $value['value'] = trim((string) ($value['value'] ?? ''));

      if ($default_country_code !== '') {
        $country_code = trim((string) ($value['country_code'] ?? ''));
        if (!preg_match(PhoneNumberHandler::COUNTRY_CODE_PATTERN, $country_code)) {
          $country_code = $default_country_code;
        }
        // A country code typed into the number input wins over the country
        // code input. Also done here, so that numbers are saved right without
        // JavaScript.
        [$country_code, $national] = $this->phoneNumberHandler->splitNumber($value['value'], $country_code);
        $value['value'] = $national === '' ? '' : $country_code . ' ' . $national;
      }
      unset($value['country_code']);
    }
    return $values;
  }

}
