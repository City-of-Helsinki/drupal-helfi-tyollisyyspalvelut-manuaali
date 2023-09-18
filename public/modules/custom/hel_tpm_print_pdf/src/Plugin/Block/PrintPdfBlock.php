<?php

namespace Drupal\hel_tpm_print_pdf\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provide a block with entity print link.
 *
 * @Block(
 *   id = "hel_print_pdf_block",
 *   admin_label = @Translation("Print pdf block"),
 *   category = @Translation("Print pdf"),
 * )
 */
class PrintPdfBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $output = [];
    $base_url = \Drupal::request()->getRequestUri();
    $output['#markup'] = '<a class="button log" href="' . $base_url . '?' . $_SERVER['QUERY_STRING'] . '">View PDF</a>';
    $output['#attached']['library'] = 'hel_tpm_print_pdf/hel_tpm_print_pdf';
    return $output;
  }

}
