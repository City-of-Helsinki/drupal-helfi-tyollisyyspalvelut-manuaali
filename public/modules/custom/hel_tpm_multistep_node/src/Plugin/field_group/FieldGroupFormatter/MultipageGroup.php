<?php

namespace Drupal\hel_tpm_multistep_node\Plugin\field_group\FieldGroupFormatter;

use Drupal\Component\Utility\Html;
use Drupal\field_group\FieldGroupFormatterBase;

/**
 * Plugin implementation of the 'multipage' formatter.
 *
 * @FieldGroupFormatter(
 *   id = "multipage_group",
 *   label = @Translation("Multipage group"),
 *   description = @Translation("Turns javascript multipage groups to ajaxified version."),
 *   supported_contexts = {
 *     "form",
 *     "view",
 *   }
 * )
 */
class MultipageGroup extends FieldGroupFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function preRender(&$element, $rendering_object) {

    $element += [
      '#type' => 'multipage_group',
      '#title' => Html::escape($this->t('@label', ['@label' => $this->getLabel()])),
      '#pre_render' => [],
      '#attributes' => [],
    ];

    if ($this->getSetting('description')) {
      $element += [
        '#description' => $this->getSetting('description'),
      ];

    }

    if ($this->getSetting('id')) {
      $element['#id'] = Html::getId($this->getSetting('id'));
    }

    $classes = $this->getClasses();
    if (!empty($classes)) {
      $element['#attributes'] += ['class' => $classes];
    }

    if ($this->getSetting('required_fields')) {
      $element['#attached']['library'][] = 'field_group/formatter.fieldset';
      $element['#attached']['library'][] = 'field_group/core';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {

    $form = parent::settingsForm();

    $options = [
      $this->t('None'),
      $this->t('Label only'),
      $this->t('Step 1 of 10'),
      $this->t('Step 1 of 10 [Label]'),
    ];
    $form['page_header'] = [
      '#title' => $this->t('Format page title'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('page_header'),
      '#options' => $options,
      '#weight' => 20,
    ];

    $options = [
      $this->t('No'),
      $this->t('Format 1 / 10'),
      $this->t('The count number only'),
    ];
    $form['page_counter'] = [
      '#title' => $this->t('Add a page counter at the bottom'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('page_counter'),
      '#options' => $options,
      '#weight' => 21,
    ];

    $form['move_button'] = [
      '#title' => $this->t('Move submit button to last multipage'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('move_button'),
      '#weight' => 22,
      '#options' => [
        $this->t('No'),
        $this->t('Yes'),
      ],
    ];

    $form['ajaxify'] = [
      '#title' => $this->t('Ajaxify'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('ajaxify'),
      '#weight' => 23,
      '#options' => [0 => $this->t('No'), 1 => $this->t('Yes')],
      '#description' => $this->t('If enabled navigation to next/prev pages will be done using ajax instead of simple javascript'),
    ];
    $form['nonjs_multistep'] = [
      '#title' => $this->t('Non Javascript Multistep'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('nonjs_multistep'),
      '#weight' => 24,
      '#options' => [0 => $this->t('No'), 1 => $this->t('Yes')],
      '#description' => $this->t('If enabled and ajaxify option is disabled no javascript will be used for form submision or navigration between steps, the form will be refreshed. useful for debugging or very complex multistep forms'),
      '#states' => [
        'visible' => [
          ':input[name*="[settings][ajaxify]"]' => ['value' => 1],
        ],
      ],
    ];

    $form['scroll_top'] = [
      '#title' => $this->t('Scroll to top'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('scroll_top'),
      '#weight' => 25,
      '#options' => [
        $this->t('No'),
        $this->t('Yes'),
      ],
      '#description' => $this->t('Scroll to the top of the page on step change.'),
      '#states' => [
        'visible' => [
          ':input[name*="[settings][ajaxify]"]' => ['value' => 1],
        ],
      ],
    ];

    $form['button_label'] = [
      '#title' => $this->t('Change button label?'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('button_label'),
      '#weight' => 26,
      '#states' => [
        'visible' => [
          ':input[name*="[settings][ajaxify]"]' => ['value' => 1],
        ],
      ],
    ];

    $form['button_label_next'] = [
      '#title' => $this->t('Button label next'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('button_label_next'),
      '#weight' => 27,
      '#states' => [
        'visible' => [
          ':input[name*="[settings][ajaxify]"]' => ['value' => 1],
          ':input[name*="[settings][button_label]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['button_label_prev'] = [
      '#title' => $this->t('Button label prev'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('button_label_prev'),
      '#weight' => 28,
      '#states' => [
        'visible' => [
          ':input[name*="[settings][ajaxify]"]' => ['value' => 1],
          ':input[name*="[settings][button_label]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $summary = parent::settingsSummary();

    if ($this->getSetting('required_fields')) {
      $summary[] = $this->t('Mark as required');
    }

    if ($this->getSetting('description')) {
      $summary[] = $this->t(
        'Description : @description',
        ['@description' => $this->getSetting('description')]
      );
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultContextSettings($context) {
    $defaults = [
      'description' => '',
      'move_button' => 1,
      'ajaxify' => 1,
      'nonjs_multistep' => 0,
      'scroll_top' => 0,
      'button_label' => 0,
    ] + parent::defaultSettings($context);

    if ($context == 'form') {
      $defaults['required_fields'] = 1;
    }

    return $defaults;
  }

}
