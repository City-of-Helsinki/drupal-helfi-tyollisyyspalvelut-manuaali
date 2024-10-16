<?php

namespace Drupal\hel_tpm_group\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides group filtering with publishing status.
 *
 * @EntityReferenceSelection(
 *   id = "hel_tpm_group_group_selection",
 *   label = @Translation("General group selection"),
 *   group = "hel_tpm_group_group_selection",
 *   entity_types = {"group"},
 *   weight = 1
 * )
 */
class GroupSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'published_filter' => '_none',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['published_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Published status'),
      '#required' => FALSE,
      '#empty_value' => '_none',
      '#options' => [
        'published' => $this->t('Published'),
        'unpublished' => $this->t('Unpublished'),
      ],
      '#default_value' => $config['published_filter'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    $configuration = $this->getConfiguration();

    match($configuration['published_filter']) {
      'published' => $query->condition('status', 1),
      'unpublished' => $query->condition('status', 0),
      default => NULL,
    };

    return $query;
  }

}
