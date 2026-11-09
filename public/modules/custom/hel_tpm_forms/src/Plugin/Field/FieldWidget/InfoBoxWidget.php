<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_forms\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\markup\Plugin\Field\FieldWidget\MarkupWidget;

/**
 * Markup widget rendering the markup of the field as an info box.
 *
 * The text of the info box is the markup setting of the field, so it is
 * edited in the field settings and translated with configuration translation.
 */
#[FieldWidget(
  id: 'hel_tpm_forms_info_box',
  label: new TranslatableMarkup('Info box'),
  field_types: ['markup'],
)]
class InfoBoxWidget extends MarkupWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['markup'] = [
      '#theme' => 'hel_tpm_info_box',
      '#content' => $element['markup'],
    ];
    return $element;
  }

}
