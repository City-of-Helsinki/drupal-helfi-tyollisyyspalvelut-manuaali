<?php

namespace Drupal\hel_tpm_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'SearchHeader' Block.
 *
 * @Block(
 *   id = "search_header",
 *   admin_label = @Translation("Search header"),
 *   category = "search header",
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
