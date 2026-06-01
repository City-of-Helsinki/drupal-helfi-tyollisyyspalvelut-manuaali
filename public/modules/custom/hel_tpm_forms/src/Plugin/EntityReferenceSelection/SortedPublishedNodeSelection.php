<?php

namespace Drupal\hel_tpm_forms\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\node\Plugin\EntityReferenceSelection\NodeSelection;

/**
 * Implements Entity Reference Selection by sorting published nodes.
 */
#[EntityReferenceSelection(
  id: "published_node_selection",
  label: new TranslatableMarkup("Alphabetically sorted published nodes"),
  group: "published_node_selection",
  weight: 1,
  entity_types: ["node"],
)]
class SortedPublishedNodeSelection extends NodeSelection {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'target_bundles' => NULL,
      'sort' => [
        'field' => 'title',
        'direction' => 'ASC',
      ],
      'auto_create' => FALSE,
      'auto_create_bundle' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    if (isset($form['sort'])) {
      unset($form['sort']);
    }

    if (isset($form['auto_create'])) {
      unset($form['auto_create']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS'): QueryInterface {
    $query = parent::buildEntityQuery($match, $match_operator);

    $query->condition('status', NodeInterface::PUBLISHED);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0): array {
    $options = parent::getReferenceableEntities($match, $match_operator, $limit);
    if (empty($options)) {
      return [];
    }

    // The query result is already sorted using the configured field. This order
    // does not take into account that when the label is translated, the nodes
    // are no longer sorted alphabetically. By sorting the options here,
    // both the translated and untranslated nodes are shown in the correct
    // order.
    foreach ($options as &$subArray) {
      asort($subArray, SORT_STRING);
    }
    unset($subArray);

    return $options;
  }

}
