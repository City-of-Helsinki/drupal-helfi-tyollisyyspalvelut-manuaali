<?php

namespace Drupal\hel_tpm_tmgmt\Form;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
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
      $field_name = $this->getFieldNameFromDataKey($key);
      $maxlength = $this->getFieldMaxlength($field_name);

      if (empty($maxlength)) {
        continue;
      }

      $translation_key = str_replace('][', '|', $key);
      $group_name = substr($translation_key, 0, strrpos($translation_key, '|'));
      if (empty($group_name)) {
        continue;
      }

      if (!empty($review_element[$group_name][$translation_key]['translation'])) {
        $element = &$review_element[$group_name][$translation_key]['translation'];
        $element['#maxlength'] = $maxlength;
        $element['#attributes']['data-maxlength'] = $maxlength;
        $element['#attributes']['class'][] = 'maxlength';
      }
    }

    return $review_element;
  }

  /**
   * Gets maxlength setting for a field from the source entity form display.
   *
   * @param string|null $field_name
   *   The field machine name.
   *
   * @return int|null
   *   The configured maxlength value, or NULL when unavailable.
   */
  protected function getFieldMaxlength(?string $field_name): ?int {
    if (!$field_name) {
      return NULL;
    }

    $job_item = $this->getEntity();

    $entity_type = $job_item->get('item_type')->value;
    $entity_id = $job_item->get('item_id')->value;

    if (!$entity_type || !$entity_id) {
      return NULL;
    }

    $source_entity = \Drupal::entityTypeManager()
      ->getStorage($entity_type)
      ->load($entity_id);

    if (!$source_entity || !$source_entity->hasField($field_name)) {
      return NULL;
    }

    $form_display = EntityFormDisplay::load($entity_type . '.' . $source_entity->bundle() . '.default');
    if (!$form_display) {
      return NULL;
    }

    $component = $form_display->getComponent($field_name);
    $maxlength = $component['third_party_settings']['maxlength']['maxlength_js'] ?? NULL;

    return !empty($maxlength) && (int) $maxlength > 0 ? (int) $maxlength : NULL;
  }

  /**
   * Extracts the field name from a flattened TMGMT data key.
   *
   * @param string $key
   *   The flattened TMGMT data key.
   *
   * @return string|null
   *   The field name, or NULL when it cannot be resolved.
   */
  protected function getFieldNameFromDataKey(string $key): ?string {
    $parts = explode('][', $key);

    return !empty($parts[0]) ? $parts[0] : NULL;
  }

}
