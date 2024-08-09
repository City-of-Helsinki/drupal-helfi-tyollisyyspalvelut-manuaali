<?php

namespace Drupal\hel_tpm_print_pdf\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provide a block with entity print link.
 *
 * @Block(
 *   id = "hel_print_pdf_block",
 *   admin_label = @Translation("Print pdf block"),
 *   category = "print pdf",
 * )
 */
class PrintPdfBlock extends BlockBase {

  /**
   * {@inheritdoc}
   *
   * @todo Redo print button logic
   */
  public function build() {
    $output = [];
    return $output;
  }

}
