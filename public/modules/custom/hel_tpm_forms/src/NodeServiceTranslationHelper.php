<?php

namespace Drupal\hel_tpm_forms;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\node\NodeTranslationHandler;

/**
 * Service field translation helper. Disables field instead of hiding.
 */
class NodeServiceTranslationHelper extends NodeTranslationHandler {

  /**
   * Process callback: determines which elements get clue in the form.
   *
   * @see \Drupal\content_translation\ContentTranslationHandler::entityFormAlter()
   */
  public function entityFormSharedElements($element, FormStateInterface $form_state, $form) {
    $node_form_ids = ['node_service_form', 'node_service_edit_form'];
    if (!in_array($form['#form_id'], $node_form_ids)) {
      parent::entityFormSharedElements($element, $form_state, $form);
    }

    static $ignored_types;

    // @todo Find a more reliable way to determine if a form element concerns a
    //   multilingual value.
    if (!isset($ignored_types)) {
      $ignored_types = array_flip(['actions', 'value', 'hidden', 'vertical_tabs', 'token', 'details', 'link']);
    }

    /** @var \Drupal\Core\Entity\ContentEntityForm $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_object->getEntity();
    $display_translatability_clue = !$entity->isDefaultTranslationAffectedOnly();
    $hide_untranslatable_fields = $entity->isDefaultTranslationAffectedOnly() && !$entity->isDefaultTranslation();
    $translation_form = $form_state->get(['content_translation', 'translation_form']);
    $display_warning = FALSE;

    // We use field definitions to identify untranslatable field widgets to be
    // hidden. Fields that are not involved in translation changes checks should
    // not be affected by this logic (the "revision_log" field, for instance).
    $field_definitions = array_diff_key($entity->getFieldDefinitions(), array_flip($this->getFieldsToSkipFromTranslationChangesCheck($entity)));

    foreach (Element::children($element) as $key) {
      if (!isset($element[$key]['#type'])) {
        $this->entityFormSharedElements($element[$key], $form_state, $form);
      }
      else {
        // Ignore non-widget form elements.
        if (isset($ignored_types[$element[$key]['#type']])) {
          continue;
        }
        // Elements are considered to be non multilingual by default.
        if (empty($element[$key]['#multilingual'])) {
          // If we are displaying a multilingual entity form we need to provide
          // translatability clues, otherwise the non-multilingual form elements
          // should be hidden.
          if (!$translation_form) {
            if ($display_translatability_clue) {
              $this->addTranslatabilityClue($element[$key]);
            }
            // Hide widgets for untranslatable fields.
            if ($hide_untranslatable_fields && isset($field_definitions[$key])) {
              $element[$key]['#disabled'] = TRUE;
              $display_warning = TRUE;
            }
          }
          else {
            $element[$key]['#disabled'] = TRUE;
          }
        }
      }
    }

    if ($display_warning) {
      $url = $entity->getUntranslated()->toUrl('edit-form')->toString();
      $message['warning'][] = $this->t('Fields that apply to all languages are disabled to avoid conflicting changes. <a href=":url">Edit them on the original language form</a>.', [':url' => $url]);
      // Explicitly renders this warning message. This prevents repetition on
      // AJAX operations or form submission. Other messages will be rendered in
      // the default location.
      // @see \Drupal\Core\Render\Element\StatusMessages.
      $element['hidden_fields_warning_message'] = [
        '#theme' => 'status_messages',
        '#message_list' => $message,
        '#weight' => -100,
        '#status_headings' => [
          'warning' => $this->t('Warning message'),
        ],
      ];
    }

    return $element;
  }

}
