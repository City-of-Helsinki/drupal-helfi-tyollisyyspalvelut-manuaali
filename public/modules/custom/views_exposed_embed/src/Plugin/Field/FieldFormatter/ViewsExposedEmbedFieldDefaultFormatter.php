<?php

declare(strict_types=1);

namespace Drupal\views_exposed_embed\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Drupal\views_exposed_embed\Plugin\Field\FieldType\ViewsExposedEmbedFieldItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a formatter for the Views Exposed Embed field.
 *
 * Allows the rendering of exposed filters configured in a view and manages
 * their interaction with the Views Exposed Embed field type.
 */
#[FieldFormatter(
  id: 'views_exposed_embed_field_default',
  label: new TranslatableMarkup('Default'),
  field_types: [
    'views_exposed_embed_field',
  ],
)]
final class ViewsExposedEmbedFieldDefaultFormatter extends FormatterBase {

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private Request $currentRequest;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, Request $current_request) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->currentRequest = $current_request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('request_stack')->getCurrentRequest()
    );
  }

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
  public function settingsSummary(): array {
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
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $form['exposed_filters'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Exposed filters'),
      '#description' => $this->t('Exposed filters will be displayed on the node view page.'),
      '#options' => $this->getViewsExposedFiltersList(),
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
      $element[$delta] = $this->renderView($item, $delta);
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
    $filters = $this->buildFilters($item);

    $view = $this->prepareViewRender($filters);
    if (empty($view)) {
      return [];
    }

    // Create a preview render from view.
    $view->preview();

    $render_array = $view->buildRenderable();

    // Create custom exposed filter list.
    $render_array['exposed_filters'] = $this->createFilterForm($view);
    $render_array['#arguments'][] = Json::encode(['exposed_embed' => $filters]);

    return !empty($render_array) ? $render_array : [];
  }

  /**
   * Builds and retrieves the filters for the provided view item.
   *
   * @param \Drupal\views\ViewsExposedEmbedFieldItem $item
   *   The views exposed embed field item from which filters are derived.
   *
   * @return array
   *   An array of filters after merging with the exposed filter selection.
   */
  protected function buildFilters(ViewsExposedEmbedFieldItem $item): array {
    $filters = $item->getValue();
    $filters = reset($filters);
    $filters = array_merge($filters, $this->getExposedFilterSelection());
    return array_filter($filters);
  }

  /**
   * Retrieves the selected values for exposed filters.
   *
   * This method processes the exposed filters settings and checks for values
   * submitted via the POST request. Only filters with non-empty values in
   * both settings and POST data are included in the selection.
   *
   * @return array
   *   An associative array of exposed filter IDs as keys and their
   *   corresponding submitted values as values. If no valid filters are
   *   found, an empty array is returned.
   */
  protected function getExposedFilterSelection(): array {
    $filters = $this->getSetting('exposed_filters') ?? [];
    $selection = [];
    if (empty($filters)) {
      return [];
    }

    $query = $this->currentRequest->query;
    foreach ($filters as $filter => $value) {
      if (empty($value)) {
        continue;
      }
      $filter_value = $query->all($filter);
      if (empty($filter_value)) {
        continue;
      }
      $selection[$filter] = $filter_value;
    }
    return $selection;
  }

  /**
   * Creates and returns a filter form for exposed filters in a view.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view to which the filter form is being attached.
   *
   * @return array
   *   A renderable array representing the filter form, or an empty array if no
   *   exposed filters are configured.
   */
  protected function createFilterForm(ViewExecutable $view): array {
    $filter_form = [];
    $filters = $this->getSetting('exposed_filters') ?? [];

    if (empty($filters)) {
      return $filter_form;
    }

    if ($view->display_handler->usesExposed()) {
      /** @var \Drupal\views\Plugin\views\exposed_form\ExposedFormPluginInterface $exposed_form */
      $exposed_form = $view->display_handler->getPlugin('exposed_form');
      $output = $exposed_form->renderExposedForm(TRUE);
      if (!empty($output)) {
        $output += [
          '#view' => $view,
          '#display_id' => $view->current_display,
        ];
      }
    }

    foreach ($filters as $filter => $value) {
      if ($value !== 0) {
        continue;
      }
      $output[$filter]['#access'] = FALSE;
    }

    return $output;
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
  protected function prepareViewRender(array $filter_values): ?ViewExecutable {
    $view = $this->getView();
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
  protected function getView(): ?ViewExecutable {
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
