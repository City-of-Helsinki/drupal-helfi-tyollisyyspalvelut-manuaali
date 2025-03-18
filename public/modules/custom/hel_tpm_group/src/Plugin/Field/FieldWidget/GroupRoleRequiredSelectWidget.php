<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsButtonsWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the 'hel_tpm_group_group_role_required_select' field widget.
 */
#[FieldWidget(
  id: 'hel_tpm_group_group_role_required_select',
  label: new TranslatableMarkup('Group Role Required Select'),
  field_types: [
    'boolean',
    'entity_reference',
    'list_integer',
    'list_float',
    'list_string',
  ],
  multiple_values: TRUE,
)]
final class GroupRoleRequiredSelectWidget extends OptionsButtonsWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#required'] = TRUE;
    return $element;
  }

}
