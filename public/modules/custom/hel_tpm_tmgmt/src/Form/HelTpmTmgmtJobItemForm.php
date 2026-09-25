<?php

namespace Drupal\hel_tpm_tmgmt\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\tmgmt\Form\JobItemForm;

/**
 * Class HelTpmTmgmtJobItemForm.
 *
 * Provides customized handling for job item forms in the Translation
 * Management Tool with specific functionality for maxlength settings.
 */
class HelTpmTmgmtJobItemForm extends JobItemForm {

  /**
   * Builds the form and attaches necessary libraries.
   *
   * @param array $form
   *   An associative array containing the initial structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The modified form structure with attached libraries.
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['#attached']['library'][] = 'maxlength/maxlength';
    return $form;
  }

  /**
   * Builds and modifies the review form element for translations.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $data
   *   The form element data array containing translation keys.
   * @param string $parent_key
   *   The parent key identifying the group of elements.
   *
   * @return array|null
   *   The modified review form element array, or NULL if no element exists.
   */
  public function reviewFormElement(FormStateInterface $form_state, $data, $parent_key) {
    $review_element = parent::reviewFormElement($form_state, $data, $parent_key);
    if (empty($review_element)) {
      return $review_element;
    }

    foreach (Element::children($data) as $key) {
      $data_value = $data[$key];
      if (empty($data_value['#maxlength_js_enabled']) || $data_value['#maxlength_js_enabled'] !== TRUE) {
        continue;
      }

      $translation_key = str_replace('][', '|', $key);
      $group_name = substr($translation_key, 0, strrpos($translation_key, '|'));

      if (empty($group_name)) {
        continue;
      }

      if (!empty($review_element[$group_name][$translation_key]['translation'])) {
        $element = &$review_element[$group_name][$translation_key]['translation'];
        $element['#attributes']['data-maxlength'] = $element['#max_length'];
        $element['#attributes']['class'][] = 'maxlength';
      }
    }

    return $review_element;
  }

}
