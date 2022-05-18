<?php

namespace Drupal\hel_tpm_customer_view_link\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provide a block with customer view link
 * @Block(
 *   id = "hel_tpm_customer_view_link",
 *   admin_label = @Translation("Customer view link block"),
 *   category = @Translation("Customer view"),
 * )
 */
class CustomerViewLinkBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $output = [];
    $base_url = \Drupal::request()->getRequestUri();
    $default_view = substr($base_url, 0, strrpos($base_url,"/"));
    $output['#markup'] = '<a class="button log" href="' . $default_view .'">Customer View </a>';
    // $output['#attached']['library'] = 'hel_tpm_internal_guide/hel_tpm_internal_guide';
    return $output;
  }
}
