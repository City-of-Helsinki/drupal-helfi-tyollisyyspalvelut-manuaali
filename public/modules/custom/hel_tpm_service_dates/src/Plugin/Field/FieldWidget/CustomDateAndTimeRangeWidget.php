<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeDefaultWidget;

/**
 * Defines the custom date range field widget.
 */
#[FieldWidget(
  id: "hel_tpm_service_dates_custom_date_and_time_range_widget",
  label: new TranslatableMarkup("Custom Date and Time widget"),
  field_types: ["daterange"]
)]
final class CustomDateAndTimeRangeWidget extends DateRangeDefaultWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#attached']['library'][] = 'hel_tpm_service_dates/custom_date_range_widget';
    $element['#attached']['drupalSettings']['hel_tpm_service_dates']['field_name'][] = sprintf('.field--name-%s', Html::cleanCssIdentifier($items->getName()));

    $element['value']['#date_date_format'] = 'd.m.Y';
    $element['value']['#date_time_format'] = 'H:i';
    $element['value']['#date_date_element'] = 'text';
    $element['value']['#date_time_element'] = 'text';

    $element['end_value']['#date_time_format'] = 'H:i';
    $element['end_value']['#date_time_element'] = 'text';
    $element['end_value']['#date_date_element'] = 'none';
    $element['end_value']['#title'] = $this->t('Ending time');

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateStartEnd(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $start_date = $element['value']['#value']['object'];
    $end_date = &$element['end_value']['#value']['object'];

    if ($start_date instanceof DrupalDateTime && $end_date instanceof DrupalDateTime) {
      $end_date->setDate($start_date->format('Y'), $start_date->format('m'), $start_date->format('d'));
    }

    parent::validateStartEnd($element, $form_state, $complete_form);
  }

}
