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
    if ($this->view->search_language_filter === TRUE) {
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

    foreach ($handler->facet_results as $result) {
      $label = $this->formatOptionLabel($result);
      if (empty($label)) {
        continue;
      }
      $field['#options'][$result->getRawValue()] = $label;
    }

  }

  /**
   * Creates a label for a given result option.
   *
   * @param \Drupal\facets\Result\Result $result
   *   The result object for which the label is being created.
   *
   * @return string|null
   *   The generated label with the language name and count, or NULL if the
   *   language code is not found.
   */
  protected function formatOptionLabel(Result $result) {
    $langcode = $result->getRawValue();

    $label = $this->t('Results in @language', ['@language' => $this->languageManager->getLanguage($langcode)->getName()]);

    return sprintf('%s <span class="count">(%s)</span>', $label, $result->getCount());
  }

}
