<?php

namespace Drupal\hel_tpm_internal_guide\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provide a block with internal links
 *
 * @Block(
 *   id = "hel_print_internal_guide",
 *   admin_label = @Translation("Internal guide links"),
 *   category = @Translation("Internal guide"),
 * )
 */
class InternalGuideBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $output = [];
    $base_url = \Drupal::request()->getRequestUri();
    $path = rtrim($base_url, '/') . '/';
    $output['#markup'] = '<a class="button log" href="'. $path .'sisainen' . '">Internal guide</a>';
    // $output['#attached']['library'] = 'hel_tpm_internal_guide/hel_tpm_internal_guide';
    return $output;
  }
}
