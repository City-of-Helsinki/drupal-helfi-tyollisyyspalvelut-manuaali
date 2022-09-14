<?php

namespace Drupal\hel_tpm_forms\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

/**
 * Provides a footer content block.
 *
 * @Block(
 *   id = "hel_tpm_navigation",
 *   admin_label = @Translation("Hel TPM Navigation"),
 *   category = @Translation("Custom"),
 * )
 */
class HelTpmNavigationBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $content = [];
    $render_array = [
      '#theme' => 'hel_tpm_navigation',
      '#content' => $content,
    ];

    return $render_array;
  }

}
