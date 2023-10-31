<?php

namespace Drupal\hel_tpm_group\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entitygroupfield\Plugin\Field\FieldWidget\EntityGroupFieldAutocompleteWidget;

/**
 * Plugin implementation of the 'entitygroupfield_autocomplete_widget' widget.
 *
 * @FieldWidget(
 *   id = "hel_tpm_group_autocomplete_widget",
 *   label = @Translation("Hel Group autocomplete"),
 *   field_types = {
 *     "entitygroupfield"
 *   }
 * )
 */
class HtgEntityGroupFieldAutocompleteWidget extends EntityGroupFieldAutocompleteWidget {

  /**
   * Permission to check user against.
   *
   * @var string
   */
  private $permission = 'access htg entity group field autocomplete widget';

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    // @todo Change the autogenerated stub.
    $completed_widget_form = parent::form($items, $form, $form_state, $get_delta);
    if (!$this->currentUser->hasPermission($this->permission)) {
      $completed_widget_form['widget']['add_more']['#access'] = FALSE;
    }
    return $completed_widget_form;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $parents = $element['#field_parents'];

    if (!$this->currentUser->hasPermission($this->permission)) {
      $state = static::getWidgetState($parents, $field_name, $form_state);
      $state['gcontent'][$delta]['mode'] = 'edit';
      static::setWidgetState($parents, $field_name, $form_state, $state);
    }

    // @todo Change the autogenerated stub.
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    if (!$this->currentUser->hasPermission($this->permission)) {
      $element['top']['links']['#access'] = FALSE;
    }
    return $element;
  }

}
