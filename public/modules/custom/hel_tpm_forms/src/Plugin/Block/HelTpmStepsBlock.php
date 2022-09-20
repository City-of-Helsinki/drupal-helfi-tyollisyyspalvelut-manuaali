<?php

namespace Drupal\hel_tpm_forms\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\file\Entity\File;

/**
 * Provides a landing content block.
 *
 * @Block(
 *   id = "hel_tpm_steps",
 *   admin_label = @Translation("Hel TPM Steps"),
 *   category = @Translation("Custom"),
 * )
 */
class HelTpmStepsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $content = [];
    $render_array = [
      '#theme' => 'hel_tpm_steps',
      '#content' => $content,
    ];

    return $render_array;
  }

}
