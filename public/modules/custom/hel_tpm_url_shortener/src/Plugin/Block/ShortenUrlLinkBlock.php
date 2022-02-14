<?php

namespace Drupal\hel_tpm_url_shortener\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "hel_tpm_url_shortener_form",
 *   admin_label = @Translation("Url Shortener"),
 *   category = @Translation("Hel TPM Url shortener")
 * )
 */
class ShortenUrlLinkBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\hel_tpm_url_shortener\Form\ShortenUrlLinkForm');

    return $form;
  }

}
