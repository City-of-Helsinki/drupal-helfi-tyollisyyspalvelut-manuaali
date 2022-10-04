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
    $form['revision_log']['#access'] = FALSE;
    $form['created']['#access'] = FALSE;
    $form['uid']['#access'] = FALSE;

    if ($form_state->get('form_display')->getComponent('hel_tpm_service_help')) {
      $form['hel_tpm_service_help'] = [
        '#type' => 'markup',
        '#markup' => 'OHJETEKSTIÄ',
        '#theme' => 'hel_tpm_service_help',
        '#theme_wrappers' => ['form_element'],
        '#group' => 'group_details_sidebar',
      ];
    }
  }

}
