<?php

namespace Drupal\hel_tpm_service_dates;

use Drupal\ajax_dependency\AjaxDependency;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides utilities to manage dependencies and requirements for form elements.
 *
 * This class extends AjaxDependency to modify the visibility and
 * required status
 * of form elements based on certain conditions and manage dependencies between
 * source and target elements in the form.
 */
class ServiceDatesAjaxDependency extends AjaxDependency {

  /**
   * Updates widget content visibility and requirements based on a condition.
   *
   * @param bool $condition
   *   The condition to determine if the content should be
   *   accessible or required.
   * @param array $sourceElement
   *   The source element used to determine dependencies.
   * @param array &$targetElement
   *   The target element whose attributes will be modified.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   *
   * @return void
   *   Void.
   */
  public static function widgetContentIf($condition, &$sourceElement, &$targetElement, FormStateInterface $formState) {
    for ($i = 0; $i <= $targetElement['#max_delta']; $i++) {
      if (!$condition) {
        $targetElement[$i]['#access'] = FALSE;
      }
      else {
        $targetElement[$i]['#required'] = TRUE;
        self::makeDateTimeValuesRequired($targetElement[$i]);
      }
    }
    self::dependsOn($sourceElement, $targetElement, $formState);
  }

  /**
   * Updates the target element to make datetime fields required.
   *
   * @param array $targetElement
   *   The target element array, passed by reference, where datetime
   *   fields should be marked as required.
   *
   * @return void
   *   No return value, the modification is done directly on the provided
   *   $targetElement array.
   */
  public static function makeDateTimeValuesRequired(&$targetElement) {
    if (isset($targetElement['value']['#type']) && $targetElement['value']['#type'] === 'datetime') {
      $targetElement['value']['#required'] = TRUE;
    }
    if (isset($targetElement['end_value']['#type']) && $targetElement['end_value']['#type'] === 'datetime') {
      $targetElement['end_value']['#required'] = TRUE;
    }
  }

}
