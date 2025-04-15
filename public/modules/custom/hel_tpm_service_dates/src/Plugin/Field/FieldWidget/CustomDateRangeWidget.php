<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeDefaultWidget;

/**
 * Defines the 'hel_tpm_service_dates_custom_date_range' field widget.
 */
#[FieldWidget(
  id: 'hel_tpm_service_dates_date_range',
  label: new TranslatableMarkup('Custom date range'),
  field_types: ['daterange'],
)]
final class CustomDateRangeWidget extends DateRangeDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['value']['#date_date_format'] = 'd.m.Y';
    $element['value']['#date_time_format'] = 'H:i';
    $element['value']['#date_date_element'] = 'text';
    if ($element['value']['#date_time_element'] != 'none') {
      $element['value']['#date_time_element'] = 'text';
    }

    $element['end_value']['#date_time_format'] = 'H:i';
    if ($element['end_value']['#date_time_element'] != 'none') {
      $element['end_value']['#date_time_element'] = 'text';
    }

    $element['end_value']['#date_date_element'] = 'text';

    $element['#attached']['library'][] = 'hel_tpm_service_dates/custom_date_range_widget';
    $element['#attached']['drupalSettings']['hel_tpm_service_dates']['field_name'][] = sprintf('.field--name-%s', Html::cleanCssIdentifier($items->getName()));
    return $element;
  }

}
