<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_stats\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\NumberWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a widget for the "Days Since Last State Change" field type.
 *
 * This widget extends the default NumberWidget to
 * provide specific functionality
 * for the "hel_tpm_service_stats_days_since_state_change" field type.
 */
#[FieldWidget(
  id: "hel_tpm_service_stats_days_since_last_state_change_widget",
  label: new TranslatableMarkup("Days Since Last State Change Widget"),
  field_types: ["hel_tpm_service_stats_days_since_state_change"],
)]

final class DaysSinceLastStateChangeWidget extends NumberWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['value']['#default_value'] = 0;
    $element['value']['#placeholder'] = 0;
    $element['#access'] = FALSE;
    return $element;
  }

}
