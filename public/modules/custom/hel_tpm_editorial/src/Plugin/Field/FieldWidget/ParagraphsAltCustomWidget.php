<?php

namespace Drupal\hel_tpm_editorial\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;

/**
 * Defines the 'hel_tpm_editorial_paragraphs_alt_custom' field widget.
 *
 * @FieldWidget(
 *   id = "hel_tpm_editorial_paragraphs_alt_custom",
 *   label = @Translation("Paragraphs Alt Custom"),
 *   field_types = {"entity_reference_revisions"},
 * )
 */
class ParagraphsAltCustomWidget extends ParagraphsWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    $settings['add_label'] = '';
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['add_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override add label'),
      '#default_value' => $this->getSetting('add_label'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $elements = parent::form($items, $form, $form_state, $get_delta);
    $elements['widget']['#field_label'] = $this->getLabel();
    return $elements;
  }

  /**
   * Add support for overriding add button labels.
   *
   * @return array
   *   Add more elements button array.
   */
  protected function buildButtonsAddMode() {
    $add_more_elements = parent::buildButtonsAddMode();
    $add_label_override = $this->getSetting('add_label');

    // If label hasn't been overridden return elements.
    if (empty($add_label_override)) {
      return $add_more_elements;
    }

    // Go through add more elements and change button label.
    foreach ($add_more_elements as &$element) {
      if (!is_array($element)) {
        continue;
      }
      if (empty($element['#type']) || $element['#type'] !== 'submit') {
        continue;
      }
      $element['#value'] = $this->t('%label', ['%label' => $add_label_override]);
    }

    return $add_more_elements;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $widget_element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element_top = &$widget_element['top'];
    $element_top['actions']['actions']['remove_button'] = $element_top['actions']['dropdown_actions']['remove_button'];
    $element_top['actions']['actions']['remove_button']['#attributes']['class'][] = 'close-icon-button';
    $element_top['#attributes']['class'][] = 'paragraph-top-custom';
    $element_top['type']['#access'] = FALSE;
    $element_top['actions']['dropdown_actions']['#access'] = FALSE;

    return $widget_element;
  }

  /**
   * Get element label.
   *
   * @return mixed
   *   Element label.
   */
  private function getLabel() {
    $settings = $this->getSettings();
    if ($this->getPluginDefinition()['multiple_values'] === TRUE) {
      return $settings['title_plural'];
    }
    return $settings['title'];
  }

}
