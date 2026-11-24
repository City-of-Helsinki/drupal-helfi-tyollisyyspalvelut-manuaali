<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'Telephone with title' field type.
 *
 * Works like a link field, but each item has a telephone number and a title.
 * The number is validated like in core's telephone field. The title is
 * required or optional, depending on the field settings.
 *
 * @property string|null $value
 *   The telephone number.
 * @property string|null $title
 *   The title, e.g. the name of the phone service.
 */
#[FieldType(
  id: 'hel_tpm_telephone_with_title',
  label: new TranslatableMarkup('Telephone with title'),
  description: new TranslatableMarkup('A telephone number with a title, displayed as a telephone link.'),
  default_widget: 'hel_tpm_telephone_with_title_default',
  default_formatter: 'hel_tpm_telephone_with_title_link',
)]
class TelephoneWithTitleItem extends FieldItemBase {

  /**
   * The maximum length of the number, the same as in core's telephone field.
   */
  public const NUMBER_MAX_LENGTH = 256;

  /**
   * The maximum length of the title.
   */
  public const TITLE_MAX_LENGTH = 255;

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'title_required' => TRUE,
      'title_label' => '',
      'title_description' => '',
      'value_label' => '',
      'value_description' => '',
      'add_more_label' => '',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::fieldSettingsForm($form, $form_state);
    $element['title_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require title'),
      '#description' => $this->t('If unchecked, the title is optional and the telephone number can be entered alone.'),
      '#default_value' => $this->getSetting('title_required'),
    ];

    $element['title_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title label'),
      '#description' => $this->t('Leave empty to use the default label: %default.', [
        '%default' => $this->t('Title'),
      ]),
      '#default_value' => $this->getSetting('title_label'),
      '#maxlength' => 255,
    ];
    $element['title_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Title help text'),
      '#description' => $this->t('Shown below the title in the form.'),
      '#default_value' => $this->getSetting('title_description'),
      '#rows' => 2,
    ];
    $element['value_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Telephone number label'),
      '#description' => $this->t('Leave empty to use the default label: %default.', [
        '%default' => $this->t('Telephone number'),
      ]),
      '#default_value' => $this->getSetting('value_label'),
      '#maxlength' => 255,
    ];
    $element['value_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Telephone number help text'),
      '#description' => $this->t('Shown below the telephone number in the form. Leave empty to use the default: %default', [
        '%default' => $this->t('E.g. 09 310 12345 or 040 123 4567.'),
      ]),
      '#default_value' => $this->getSetting('value_description'),
      '#rows' => 2,
    ];
    $element['add_more_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('"Add another item" button label'),
      '#description' => $this->t('Used when the field allows more than one value. Leave empty to use the default label: %default.', [
        '%default' => $this->t('Add another item'),
      ]),
      '#default_value' => $this->getSetting('add_more_label'),
      '#maxlength' => 255,
    ];

    return $element;
  }

  /**
   * Gets the label of the title, the custom one or the default.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  public function getTitleLabel(): string|TranslatableMarkup {
    return $this->getSetting('title_label') ?: $this->t('Title');
  }

  /**
   * Gets the label of the telephone number, the custom one or the default.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  public function getValueLabel(): string|TranslatableMarkup {
    return $this->getSetting('value_label') ?: $this->t('Telephone number');
  }

  /**
   * Gets the help text of the title. There is no default help text.
   *
   * @return string
   *   The help text, may be empty.
   */
  public function getTitleDescription(): string {
    return (string) $this->getSetting('title_description');
  }

  /**
   * Gets the help text of the telephone number, the custom one or the default.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The help text.
   */
  public function getValueDescription(): string|TranslatableMarkup {
    return $this->getSetting('value_description') ?: $this->t('E.g. 09 310 12345 or 040 123 4567.');
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Telephone number'))
      ->setRequired(TRUE);
    $properties['title'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'value' => [
          'type' => 'varchar',
          'length' => self::NUMBER_MAX_LENGTH,
        ],
        'title' => [
          'type' => 'varchar',
          'length' => self::TITLE_MAX_LENGTH,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $value = $this->get('value')->getValue();
    $title = $this->get('title')->getValue();
    return ($value === NULL || trim($value) === '') && ($title === NULL || trim($title) === '');
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    $constraint_manager = $this->getTypedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();
    $field_label = $this->getFieldDefinition()->getLabel();

    // The number is validated the same way as in core's telephone field.
    $properties['value'] = [
      'NotBlank' => [
        'message' => $this->t('%name: %label is required.', [
          '%name' => $field_label,
          '%label' => $this->getValueLabel(),
        ]),
      ],
      'Length' => [
        'max' => self::NUMBER_MAX_LENGTH,
        'maxMessage' => $this->t('%name: %label may not be longer than @max characters.', [
          '%name' => $field_label,
          '%label' => $this->getValueLabel(),
          '@max' => self::NUMBER_MAX_LENGTH,
        ]),
      ],
    ];
    $properties['title'] = [
      'Length' => [
        'max' => self::TITLE_MAX_LENGTH,
        'maxMessage' => $this->t('%name: %label may not be longer than @max characters.', [
          '%name' => $field_label,
          '%label' => $this->getTitleLabel(),
          '@max' => self::TITLE_MAX_LENGTH,
        ]),
      ],
    ];
    if ($this->getSetting('title_required')) {
      $properties['title']['NotBlank'] = [
        'message' => $this->t('%name: %label is required.', [
          '%name' => $field_label,
          '%label' => $this->getTitleLabel(),
        ]),
      ];
    }

    $constraints[] = $constraint_manager->create('ComplexData', [
      'properties' => $properties,
    ]);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(): void {
    parent::preSave();
    if ($this->value !== NULL) {
      $this->value = trim($this->value);
    }
    if ($this->title !== NULL) {
      $this->title = trim($this->title);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition): array {
    return [
      'value' => '040 ' . random_int(1000000, 9999999),
      'title' => 'Palvelunumero',
    ];
  }

}
