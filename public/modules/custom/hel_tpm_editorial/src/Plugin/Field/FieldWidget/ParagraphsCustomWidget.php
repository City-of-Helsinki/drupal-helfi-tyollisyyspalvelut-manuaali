<?php

namespace Drupal\hel_tpm_editorial\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;

/**
 * Defines the 'hel_tpm_editorial_paragraphs_custom' field widget.
 *
 * @FieldWidget(
 *   id = "hel_tpm_editorial_paragraphs_custom",
 *   label = @Translation("Paragraphs Custom"),
 *   field_types = {"entity_reference_revisions"},
 * )
 */
class ParagraphsCustomWidget extends ParagraphsWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings();
  }

  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $elements = parent::form($items, $form, $form_state, $get_delta);
    $elements['widget']['#field_label'] = $this->getLabel();
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $widget_element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element_top = &$widget_element['top'];
    $element_top['actions']['actions']['remove_button'] = $element_top['actions']['dropdown_actions']['remove_button'];
    $element_top['#attributes']['class'][] = 'paragraph-top-custom';
    $element_top['type']['#access'] = FALSE;
    $element_top['actions']['dropdown_actions']['#access'] = FALSE;

    return $widget_element;
  }

  /**
   * @return mixed
   */
  private function getLabel() {
    $settings = $this->getSettings();
    if ($this->getPluginDefinition()['multiple_values'] === TRUE) {
      return $settings['title_plural'];
    }
    return $settings['title'];
  }
}
