<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hel_tpm_service_dates\Plugin\Field\FieldWidget\WeekdayAndTimeFieldWidget;
use Drupal\hel_tpm_service_dates\WeekdayAndTimeValue;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation weekday and time field formatter.
 */
#[FieldFormatter(
  id: 'hel_tpm_service_dates_weekday_and_time_field_default',
  label: new TranslatableMarkup('Default'),
  field_types: ['hel_tpm_service_dates_weekday_and_time_field']
)]
final class WeekdayAndTimeFieldDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, $string_translation) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('string_translation')

    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];

    foreach ($items as $delta => $item) {
      $weekdays = $item->getValue();
      if (empty($weekdays['value'])) {
        return $element;
      }

      $markup = [];
      foreach ($weekdays['value'] as $weekday => $values) {
        $string_args = ['@day' => WeekdayAndTimeFieldWidget::getTranslatedWeekdays()[$weekday]];
        foreach ($values as $key => $value) {
          $start_key = sprintf('@start%s', $key);
          $end_key = sprintf('@end%s', $key);
          $string_args[$start_key] = substr(WeekdayAndTimeValue::time($value['time']['start']) ?? '', 0, 5);
          $string_args[$end_key] = substr(WeekdayAndTimeValue::time($value['time']['end']) ?? '', 0, 5);
        }
        $markup[] = [
          '#type' => 'item',
          '#markup' => $this->stringTranslation->formatPlural(
            count($values),
            '@day at @start0 - @end0',
            '@day at @start0 - @end0 and @start1 - @end1', $string_args
          ),
        ];
      }
      $element[$delta] = [
        '#type' => 'container',
        'items' => $markup,
      ];
    }

    $element['#cache'] = [
      'tags' => $items->getEntity()->getCacheTags(),
      'max-age' => $items->getEntity()->getCacheMaxAge(),
    ];

    return $element;
  }

}
