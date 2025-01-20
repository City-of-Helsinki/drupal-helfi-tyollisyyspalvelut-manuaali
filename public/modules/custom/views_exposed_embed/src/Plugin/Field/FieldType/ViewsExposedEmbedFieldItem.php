<?php

declare(strict_types=1);

namespace Drupal\views_exposed_embed\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\Plugin\Field\FieldType\MapItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Views;

/**
 * Provides a field type to store serialized values for Views exposed embeds.
 *
 * This field type allows for the storage of configuration regarding Views,
 * specifically view ID and display ID settings.*/
#[FieldType(
  id: "views_exposed_embed_field",
  label: new TranslatableMarkup("Views exposed embed"),
  description: new TranslatableMarkup("An entity field for storing a serialized array of values."),
  default_widget: "views_exposed_embed_field_widget",
  default_formatter: "views_exposed_embed_field_default",
  no_ui: FALSE,
  list_class: ViewsExposedEmbedFieldItemList::class,
)]

final class ViewsExposedEmbedFieldItem extends MapItem {

  const DISPLAY_ID_WRAPPER = 'edit-select-display-id';

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    $settings = [
      'view_id' => '',
      'display_id' => '',
    ];
    return $settings + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $element, FormStateInterface $element_state): array {
    $element = parent::fieldSettingsForm($element, $element_state);

    // Hide the handler settings since configuration is not useful.
    $element['handler']['#type'] = 'fieldset';
    $element['handler']['#attributes']['hidden'] = TRUE;

    $view_options = $this->getViewOptions(FALSE);
    $element['view_id'] = [
      '#type' => 'select',
      '#options' => $view_options,
      '#title' => $this->t('View ID'),
      '#default_value' => $this->getSetting('view_id'),
      '#description' => $this->t('Views available for content authors. Leave empty to allow all.'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'getDisplayTypeOptionsAjax'],
        'disable-refocus' => TRUE,
        'event' => 'change',
        'wrapper' => $this::DISPLAY_ID_WRAPPER,
      ],
    ];

    $user_input = $element_state->getUserInput()['settings'];

    if (!empty($user_input['view_id'])) {
      $view_id = $user_input['view_id'];
    }
    elseif ($this->getSetting('view_id')) {
      $view_id = $this->getSetting('view_id');
    }
    else {
      $view_id = array_key_first($view_options);
    }

    $display_options = $this->getDisplayOptions($view_id);
    // Render filtered display id selection.
    $element['display_id'] = [
      '#type' => 'select',
      '#prefix' => '<div id="' . $this::DISPLAY_ID_WRAPPER . '">',
      '#suffix' => '</div>',
      '#options' => $display_options,
      '#title' => $this->t('Display ID'),
      '#default_value' => $this->getSetting('display_id'),
      '#description' => $this->t('Display types available for content authors. Leave empty to allow all.'),
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * Get an options array of views.
   *
   * @param bool $filter
   *   (optional) Flag to filter the output using the 'allowed_views' setting.
   *
   * @return array
   *   The array of options.
   */
  public function getViewOptions($filter = TRUE) {
    $views_options = [];
    $allowed_views = $filter ? array_filter($this->getSetting('allowed_views')) : [];
    foreach (Views::getEnabledViews() as $key => $view) {
      if (empty($allowed_views) || isset($allowed_views[$key])) {
        $views_options[$key] = FieldFilteredMarkup::create($view->get('label'));
      }
    }
    natcasesort($views_options);

    return $views_options;
  }

  /**
   * Retrieves the display type options for an AJAX callback.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The display type options array.
   */
  public function getDisplayTypeOptionsAjax(array &$form, FormStateInterface $form_state): array {
    return $form['settings']['display_id'];
  }

  /**
   * Get an options array of display configurations for a specific entity.
   *
   * @param string|int $entity_id
   *   The ID of the entity for which display options are to be retrieved.
   *
   * @return array
   *   The array of display options, sorted in a case-insensitive natural order.
   */
  public function getDisplayOptions($entity_id) {
    $display_options = [];
    $views = Views::getEnabledViews();
    if (isset($views[$entity_id])) {
      foreach ($views[$entity_id]->get('display') as $key => $display) {
        if (isset($display['display_options']['enabled']) && !$display['display_options']['enabled']) {
          continue;
        }
        $display_options[$key] = FieldFilteredMarkup::create($display['display_title']);
      }
      natcasesort($display_options);
    }

    return $display_options;
  }

}
