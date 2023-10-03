<?php

namespace Drupal\hel_tpm_service_dates\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Plugin\Field\FieldWidget\InlineParagraphsWidget;

/**
 * Defines the 'hel_tpm_service_dates_service_time_and_place_widget' widget.
 *
 * @FieldWidget(
 *   id = "hel_tpm_service_dates_service_time_and_place_widget",
 *   label = @Translation("Service time and place widget"),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class ServiceTimeAndPlaceWidgetWidget extends InlineParagraphsWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['top']['links']['remove_button']['#paragraphs_mode'] = 'removed';
    return $element;
  }

}
