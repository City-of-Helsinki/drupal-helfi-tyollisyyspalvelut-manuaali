<?php

declare(strict_types=1);

namespace Drupal\views_exposed_embed\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;
use Drupal\views_exposed_embed\Plugin\Field\FieldType\ViewsExposedEmbedFieldItem;

/**
 * Plugin implementation of the 'views_exposed_embed_field_default' formatter.
 *
 * @FieldFormatter(
 *   id = "views_exposed_embed_field_default",
 *   label = @Translation("Default"),
 *   field_types = {"views_exposed_embed_field"},
 * )
 */
final class ViewsExposedEmbedFieldDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        // Declare a setting named 'text_length', with
        // a default value of 'short'.
      'exposed_filters' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Show exposed embed filters');
    $filters = $this->getSetting('exposed_filters') ?? [];
    foreach ($filters as $filter => $value) {
      if (empty($value)) {
        unset($filters[$filter]);
      }
    }
    $summary[] = $this->t('Exposed filters: @filters', ['@filters' => implode(', ', $filters)]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $filters = $this->getViewsExposedFiltersList();
    $form['exposed_filters'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Exposed filters'),
      '#description' => $this->t('Exposed filters will be displayed on the node view page.'),
      '#options' => $filters,
      '#default_value' => $this->getSetting('exposed_filters'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];

    foreach ($items as $delta => $item) {
      $render_view = $this->renderView($item);
      $element[$delta] = $render_view;
    }

    return $element;
  }

  /**
   * Render view for given field item.
   *
   * @param \Drupal\views_exposed_embed\Plugin\Field\FieldType\ViewsExposedEmbedFieldItem $item
   *   Views exposed embed field item.
   *
   * @return array
   *   View render array.
   */
  protected function renderView(ViewsExposedEmbedFieldItem $item): array {
    $filter_values = $item->getValue();
    $filter_values = reset($filter_values);

    if (!$filter_values) {
      return [];
    }

    $view = $this->prepareViewRender($filter_values);
    if (empty($view)) {
      return [];
    }

    $view->preview();

    $filters = $this->getSetting('exposed_filters') ?? [];

    // Disable exposed filters that are not selected in the filters array.
    if (!empty($view->exposed_widgets)) {
      foreach ($view->exposed_widgets as $key => &$widget) {
        // Disable the filter if it is not selected or not present in filters.
        if (isset($filters[$key]) && empty($filters[$key])) {
          $widget['#access'] = FALSE;
        }
      }
    }
    $render_array = $view->buildRenderable();

    return !empty($render_array) ? $render_array : [];
  }

  /**
   * Determines whether all exposed filters are hidden.
   *
   * @param array $filters
   *   An array of exposed filters to be checked. Each filter's value indicates
   *   whether it is exposed or not.
   *
   * @return bool
   *   Returns TRUE if all filters are hidden (i.e., empty), otherwise FALSE.
   */
  protected function hideExposedFilters(array $filters) {
    foreach ($filters as $filter) {
      $is_not_empty = !empty($filter);
      if ($is_not_empty) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Prepares and returns a view render with applied filter values.
   *
   * @param array $filter_values
   *   An associative array of filter values to be applied to the view's
   *   exposed input.
   *
   * @return \Drupal\views\ViewExecutable
   *   The prepared view executable instance.
   */
  protected function prepareViewRender(array $filter_values) {
    $view_id = $this->getFieldSetting('view_id');
    $display_id = $this->getFieldSetting('display_id');
    $view = Views::getView($view_id);
    $view->setDisplay($display_id);

    $exposed_input = $view->getExposedInput();
    $exposed_input = array_merge($exposed_input, $filter_values);
    $view->setExposedInput($exposed_input);
    $view->initHandlers();

    return $view;
  }

  /**
   * Retrieves and sets up a view instance based on the field settings.
   *
   * @return \Drupal\views\ViewExecutable|null
   *   The view executable instance if the view exists, or NULL otherwise.
   */
  protected function getView() {
    $view_id = $this->getFieldSetting('view_id');
    $display_id = $this->getFieldSetting('display_id');
    $view = Views::getView($view_id);
    $view->setDisplay($display_id);

    return $view;
  }

  /**
   * Retrieves a list of exposed filters and their titles from the view.
   *
   * @return array
   *   An associative array where the keys are the exposed filter machine names
   *   and the values are their corresponding titles.
   */
  protected function getViewsExposedFiltersList(): array {
    $view = $this->getView();
    $view->initHandlers();
    $filters = [];
    foreach ($view->filter as $filter) {
      if (!$filter->isExposed()) {
        continue;
      }
      $filters[$filter->options['expose']['identifier']] = $filter->configuration['title'];
    }
    return $filters;
  }

}
