<?php

namespace Drupal\hel_tpm_editorial\Utility;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFormInterface;

/**
 * ServiceFormHelper.
 */
class ServiceFormHelper {

  /**
   * Returns the entity extra field info.
   */
  public static function entityExtraFieldInfo() {
    $extra['node']['service']['form']['hel_tpm_service_help'] = [
      'label' => t('Help text'),
      'description' => '',
      'weight' => 0,
      'visible' => TRUE,
    ];
    return $extra;
  }

  /**
   * Alters node form.
   */
  public static function formNodeFormAlter(&$form, FormStateInterface $form_state, $form_id): void {
    if ($form_state->get('form_display')->getComponent('hel_tpm_service_help')) {
      $form['hel_tpm_service_help'] = [
        '#type' => 'markup',
        '#markup' => 'OHJETEKSTIÄ',
      ];
    }

    // Multistep navigation.
    $form['paging_header'] = [
      '#theme' => 'multistep_navigation',
      '#navigation' => ServiceFormHelper::pagerNavigation($form, $form_state),
    ];
    $actions = $form['actions'];
    $weight = 999;
    $form['actions']['#weight'] = $weight--;
    $form['controls'] = [
      '#type' => 'actions',
      '#weight' => $weight,
      '#attributes' => ['class' => ['page-controls']]
    ];

    $controls = ['next', 'prev'];

    $form['#submit'][0] = ['Drupal\hel_tpm_editorial\Utility\ServiceFormHelper', 'formSubmit'];
    foreach ($controls as $ctrl) {
      if (empty($actions[$ctrl])) {
        continue;
      }

      $actions[$ctrl]['#attributes']['class'][] = 'button';
      $actions[$ctrl]['#attributes']['class'][] = sprintf('btn-%s', $ctrl);

      $actions[$ctrl]['#validate'][0] = ['Drupal\hel_tpm_editorial\Utility\ServiceFormHelper', 'formValidate'];
      if (!empty($actions[$ctrl]['#submit'])) {
        $actions[$ctrl]['#submit'][0] = ['Drupal\hel_tpm_editorial\Utility\ServiceFormHelper', 'formSubmit'];
      }
      $form['controls'][$ctrl] = $actions[$ctrl];
      unset($form['actions'][$ctrl]);
    }

  }

  /**
   * Helper function to build renderable array for navigation.
   *
   * @param $form
   * @param $form_state
   *
   * @return array|false
   */
  public static function pagerNavigation($form, $form_state) {
    $storage = $form_state->getStorage();
    if (empty($storage['field_group_ajaxified_multipage_enabled']) || $storage['field_group_ajaxified_multipage_enabled'] !== TRUE) {
      return FALSE;
    }
    $navigation = [];
    $field_groups = $form['#fieldgroups'];
    $multipage = $storage['field_group_ajaxified_multipage_group'];
    $current_step = $storage['field_group_ajaxified_multipage_step']-1;
    $steps = $multipage['children'];

    foreach ($steps as $key => $step) {
      $navigation[$key] = [
        'label' => $field_groups[$step]->label,
        'active' => $key === $current_step,
        'page_done' => $current_step > $key,
      ];
    }

    return $navigation;
  }

  /**
   * Validate function for ajaxified form.
   *
   * In AJAX this is only submitted when the final submit button is clicked,
   * but in the non-javascript situation, it is submitted with every
   * button click.
   */
  public static function formValidate($form, &$form_state) {
    $entity_form = $form_state->getFormObject();

    if ($entity_form instanceof EntityFormInterface) {
      $entity_updated = $entity_form->buildEntity($form, $form_state);
      $entity_form->setEntity($entity_updated);
    }

    $parents_reverse = [];

    if (!is_null($form_state->getTriggeringElement())) {
      $triggeringelement = $form_state->getTriggeringElement();
      $parents_reverse = array_reverse($triggeringelement['#array_parents']);
    }

    // Increment or decrement the step as needed. Recover values if they exist.
    if (!isset($parents_reverse[1])) {
      $parents_reverse[1] = '';
    }

    ServiceFormHelper::negotiateStep($form_state, $parents_reverse);

    if (!is_null($form_state->getValues())) {
      $values = $form_state->getValues();
      $allval = [];
      if (!is_null($form_state->get('all'))) {
        $allval = $form_state->get('all');
      }
      foreach ($values as $key => $value) {
        if (!empty($value) && is_string($value) && strpos($key, 'form') === FALSE) {
          $allval['values'][$key] = $value;
        }
      }
      $form_state->set('all', $allval);
    }
    // If they're done, submit.
    if ($parents_reverse[1] == 'controls' && ($parents_reverse[0] == 'submit')) {
      $form_state->setRebuild(FALSE);
      return;
    }

    // Otherwise, we still have work to do.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit function for ajaxified form.
   *
   * In AJAX this is only submitted when the final submit button is clicked,
   * but in the non-javascript situation, it is submitted with every
   * button click.
   */
  public static function formSubmit($form, &$form_state) {
    $parents_reverse = [];
    if (!is_null($form_state->getTriggeringElement())) {
      $triggeringelement = $form_state->getTriggeringElement();
      $parents_reverse = array_reverse($triggeringelement['#array_parents']);
    }

    ServiceFormHelper::negotiateStep($form_state, $parents_reverse);

    // If they're done, submit.
    if ($parents_reverse[1] == 'controls' && ($parents_reverse[0] == 'submit')) {
      if (!is_null($form_state->getValues())) {
        $values = $form_state->getValues();
        $allval = [];
        if (!is_null($form_state->get('all'))) {
          $allval = $form_state->get('all');
        }
        foreach ($values as $key => $value) {
          if (!empty($value) && is_string($value) && strpos($key, 'form') === FALSE) {
            $allval['values'][$key] = $value;
          }
        }
        $form_state->set('all', $allval);
      }
      $form_state->setRebuild(FALSE);
      return;
    }

    // Otherwise, we still have work to do.
    $form_state->setRebuild(TRUE);
  }

  /**
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param $parents_reverse
   *
   * @return void
   */
  public static function negotiateStep(FormStateInterface &$form_state, $parents_reverse) {
    $step = $form_state->get('field_group_ajaxified_multipage_step');

    // Negotiate proper step.
    if ($parents_reverse[1] == 'controls') {
      if ($parents_reverse[0] == 'next' || $parents_reverse[0] == 'skip') {
        $step++;
      }

      if ($parents_reverse[0] == 'prev') {
        $step--;
      }
      $form_state->set('field_group_ajaxified_multipage_step', $step);
    }
  }

}
