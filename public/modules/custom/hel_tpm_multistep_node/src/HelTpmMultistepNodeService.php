<?php

namespace Drupal\hel_tpm_multistep_node;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Multistep node form service.
 *  Provides controls and methods to handle multistep form.
 */
class HelTpmMultistepNodeService {


  /**
   * Check if multistep is enabled for form.
   *
   * @param $form
   *
   * @return bool
   */
  public function multistepEnabled($form) : bool {
    if (empty($form['#fieldgroups'])) {
      return FALSE;
    }
    foreach ($form['#fieldgroups'] as $fieldgroup) {
      if (!is_object($fieldgroup)) {
        continue;
      }
      // Form has multipage group return TRUE.
      if ($fieldgroup->format_type == 'multipage_group') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param $step
   *
   * @return void
   */
  public function renderStep(&$form, FormStateInterface &$form_state, $step) {
    $groups = $this->getPageGroups($form);
    // If step is empty, default to first page.
    if (empty($groups[$step])) {
      $step = 0;
    }

    $this->addAjaxWrapper($form);

    $form['paging_header'] = [
      '#theme' => 'multistep_navigation',
      '#navigation' => $this->navigationItems($groups, $step)
    ];
    $items = [];
    foreach ($this->navigationItems($groups, $step) as $key => $item) {
      $items[$key] = $item['label'];
    }
    $form['paging_navigation'] = [
      '#type' => 'select',
      '#options' => $items,
      '#weight' => 0,
      '#ajax' => [
        'wrapper' => $this->getWrapperId($form),
        'callback' => [$this, 'multistepNodePageJumpSubmit']
      ]
    ];

    foreach ($groups as $key => $group) {
      $this->convertMultipageToFieldset($form, $group);
      if ($key == $step) {
        continue;
      }

      $this->disableGroup($form, $group);
    }
  }

  /**
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return void
   */
  public static function multistepNodePageJumpSubmit(&$form, FormStateInterface $form_state) {
    $form['#id'] = $form['#form_id'];
    return $form;
  }

  /**
   * @param $fieldgroups
   * @param $groups
   * @param $current_step
   *
   * @return array
   */
  private function navigationItems($groups, $current_step) : array {
    foreach ($groups as $key => $group) {
      $navigation[$key] = [
        'label' => $group->label,
        'active' => $key === $current_step,
        'page_done' => $current_step > $key,
      ];
    }
    return $navigation;
  }

  /**
   * Converts give field group to fieldset.
   *
   * @param $form
   * @param $group
   *
   * @return void
   */
  private function convertMultipageToFieldset(&$form, $group) {
    $form['#fieldgroups'][$group->group_name]->format_type = 'fieldset';
  }

  /**
   * Disable groups
   *
   * @param $form
   * @param $group
   *
   * @return void
   */
  private function disableGroup(&$form, $group) {
    $children = $group->children;
    foreach ($children as $child) {
      if (!empty($form[$child])) {
        $form[$child]['#access'] = FALSE;
        continue;
      }

      if (!empty($form['#fieldgroups'][$child])) {
        $this->disableGroup($form, $form['#fieldgroups'][$child]);
      }

      unset($form['#fieldgroups'][$child]);
    }
  }

  /**
   * @param $form
   *
   * @return array
   */
  private function getPageGroups($form) {
    $groups = [];
    $fieldgroups = $form['#fieldgroups'];
    foreach ($fieldgroups as $fieldgroup) {
      if ($fieldgroup->format_type != 'multipage') {
        continue;
      }
      $groups[$fieldgroup->weight] = $fieldgroup;
    }
    return array_values($groups);
  }

  /**
   * @param $form
   *
   * @return string
   */
  private function getWrapperId($form) {
    $wrapper_id = 'ajax-wrapper';
    if (isset($form['#form_id'])) {
      $wrapper_id = str_replace('_', '-', $form['#form_id']) . '-' . $wrapper_id;
    }
    return $wrapper_id;
  }

  /**
   * @param $form
   *
   * @return void
   */
  private function addAjaxWrapper(&$form) {
    $form['#prefix'] = '<div id="' . $this->getWrapperId($form) . '" >';
    $form['#suffix'] = '</div>';
  }
}