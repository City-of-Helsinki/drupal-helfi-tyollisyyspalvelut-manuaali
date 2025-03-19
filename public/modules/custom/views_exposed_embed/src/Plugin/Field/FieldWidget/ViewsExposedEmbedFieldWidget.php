<?php

declare(strict_types=1);

namespace Drupal\views_exposed_embed\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Defines the 'views_exposed_embed_field' field widget.
 *
 * @FieldWidget(
 *   id = "views_exposed_embed_field_widget",
 *   label = @Translation("Views Exposed Embed field"),
 *   field_types = {"views_exposed_embed_field"},
 * )
 */
final class ViewsExposedEmbedFieldWidget extends WidgetBase {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  private FormBuilderInterface $formBuilder;

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    FormBuilderInterface $form_builder,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {

    if (!empty($element["#field_parents"])) {
      $parents = array_merge($element["#field_parents"], [$items->getName()]);
      $name = array_shift($parents);
      $name .= '[' . implode('][', $parents);
    }
    else {
      $name = $items->getName();
    }
    $element['#name'] = $name;

    $item = $items->get($delta);
    $element['value'] = $this->getViewsExposedFormElements($item);

    $element['#theme_wrappers'] = ['container', 'form_element'];
    $element['#attributes']['class'][] = 'container-inline';
    $element['#attributes']['class'][] = 'hel-tpm-general-configurable-search-result-elements';
    $element['#attached']['library'][] = 'hel_tpm_general/hel_tpm_general_configurable_search_result';

    return $element;
  }

  /**
   * Get exposed filters from selected view.
   *
   * @return array
   *   Renderable array of filters.
   *
   * @throws \Drupal\Core\Form\EnforcedResponseException
   * @throws \Drupal\Core\Form\FormAjaxException
   */
  private function getViewsExposedFormElements(FieldItemInterface $item) {
    $view_id = $this->getFieldSetting('view_id');

    if (empty($view_id)) {
      return [];
    }

    $view = Views::getView($view_id);
    $view->initHandlers();

    $form_state = new FormState();
    $form_state->setFormState([
      'view' => $view,
      'display' => $view->display_handler->display,
      'exposed_form_plugin' => $view->display_handler->getPlugin('exposed_form'),
      'method' => 'get',
      'rerender' => TRUE,
      'no_redirect' => TRUE,
      'always_process' => TRUE,
    ]);
    $form = $this->formBuilder->buildForm('Drupal\views\Form\ViewsExposedForm', $form_state);

    return $this->extractExposedFormFilters($form, $item);
  }

  /**
   * Extract filter elements from exposed form.
   *
   * @param array $form
   *   Exposed filter form.
   * @param \Drupal\Core\Field\FieldItemInterface $field_item
   *   Field item interface.
   *
   * @return array
   *   Filtered exposed form.
   */
  private function extractExposedFormFilters(array $form, FieldItemInterface $field_item): array {
    $filters = [];
    $default_values = $field_item->getValue();
    $default_values = reset($default_values);

    $filter_params = [
      '#type',
      '#title',
      '#description',
      '#default_value',
      '#options',
      '#require',
      '#multiple',
      '#value',
    ];

    $empty_filter = NULL;
    foreach ($form['#info'] as $filter) {

      $field_key = $filter['value'];
      $filter_item = $form[$field_key];

      $filter = [];

      foreach ($filter_params as $key2) {
        if (empty($filter_item[$key2])) {
          continue;
        }
        $filter[$key2] = $filter_item[$key2];
      }

      $filters[$field_key] = $filter;

      if ($filter['#type'] === 'select') {
        $empty_filter = [];
      }
      $filters[$field_key]['#default_value'] = !empty($default_values[$field_key]) ? $default_values[$field_key] : $empty_filter;
    }

    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(
    array $element,
    ConstraintViolationInterface $error,
    array $form,
    FormStateInterface $form_state,
  ): array|bool {
    $element = parent::errorElement($element, $error, $form, $form_state);
    if ($element === FALSE) {
      return FALSE;
    }
    $error_property = explode('.', $error->getPropertyPath())[1];
    return $element[$error_property];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    foreach ($values as &$value) {
      if (empty($value['value'])) {
        $value['value'] = NULL;
      }
      $this->massageFilters($value['value']);
    }
    return $values;
  }

  /**
   * Massage filter proper default values.
   *
   * @param array $filters
   *   Array of filters.
   *
   * @return void
   *   -
   */
  protected function massageFilters(array &$filters) {
    foreach ($filters as &$filter) {
      if (empty($filter)) {
        continue;
      }
      foreach ($filter as $key => $value) {
        if ($value === 0 || $value === '0' || empty($value)) {
          unset($filter[$key]);
        }
      }
    }
  }

}
