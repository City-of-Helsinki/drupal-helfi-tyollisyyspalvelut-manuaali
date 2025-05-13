<?php

namespace Drupal\hel_tpm_search\Plugin\better_exposed_filters\filter;

use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\RadioButtons;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\Result\Result;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Search language select exposed filter widget.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "search_language_select",
 *   label = @Translation("Language Select"),
 * )
 */
class SearchLanguageSelect extends RadioButtons implements ContainerFactoryPluginInterface {

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Mapping of languages and their translations.
   *
   * @var array
   */
  private array $languageMap = [
    'fi' => ['fi' => 'suomeksi', 'sv' => 'ruotsiksi', 'en' => 'englanniksi'],
    'en' => ['fi' => 'finnish', 'sv' => 'swedish', 'en' => 'english'],
    'sv' => ['fi' => 'finska', 'sv' => 'svenska', 'en' => 'engelska'],
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable($filter = NULL, array $filter_options = []) {
    if (is_a($filter, 'Drupal\facets_exposed_filters\Plugin\views\filter\FacetsFilter')) {
      return TRUE;
    }
    return parent::isApplicable($filter, $filter_options);
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state) {
    if (!empty($this->view->search_language_filter) && $this->view->search_language_filter === TRUE) {
      return;
    }

    $field_id = $this->getExposedFilterFieldId();

    parent::exposedFormAlter($form, $form_state);

    if (empty($form[$field_id])) {
      return;
    }

    $field = &$form[$field_id];
    unset($field['#options']['All']);

    $handler = &$this->handler;
    $user_input = $form_state->getUserInput();
    if (!empty($user_input) && !empty($user_input[$field_id])) {
      $current_value = $user_input[$field_id];
    }
    foreach ($handler->facet_results as $result) {
      $label = $this->formatOptionLabel($result, $current_value);
      if (empty($label)) {
        continue;
      }
      $field['#options'][$result->getRawValue()] = $label;
    }

  }

  /**
   * Formats the label for an option based on given result and selected value.
   *
   * @param \Drupal\facets_result\Result $result
   *   The result object containing the raw value and count.
   * @param mixed $selected_value
   *   The currently selected value to determine if the option is active.
   *
   * @return string
   *   The formatted option label as an HTML string.
   */
  protected function formatOptionLabel(Result $result, $selected_value) {
    $langcode = $result->getRawValue();
    $current_language = $this->languageManager->getCurrentLanguage()->getId();

    $lang = $this->languageMap[$current_language][$langcode];

    $label = $this->t('<span class="text">Results in @language</span>', [
      '@language' => $lang,
    ]);

    $classes = ['count'];
    if ($selected_value === $langcode) {
      $classes[] = 'active';
    }

    return sprintf('%s <span class="%s">%s</span>', $label, implode(' ', $classes), $result->getCount());
  }

}
