<?php

namespace Drupal\hel_tpm_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'SearchHeader' Block.
 *
 * @Block(
 *   id = "search_header",
 *   admin_label = @Translation("Search header"),
 *   category = @Translation("Search header"),
 * )
 */
class SearchHeaderBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
  return [
    '#theme' => 'search_header',
    ];
  }
}
