<?php

namespace Drupal\hel_tpm_search_autosuggest\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an autosuggest block.
 *
 * @Block(
 *   id = "hel_tpm_search_autosuggest_block",
 *   admin_label = @Translation("HEL TPM Search autosuggest"),
 *   category = @Translation("Hel TPM Search Autosuggest")
 * )
 */
class SearchAutosuggestBlock extends BlockBase {

  /**
   * Form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return $this->formBuilder->getForm('\Drupal\hel_tpm_search_autosuggest\Form\SearchAutoSuggestForm');
  }

}
