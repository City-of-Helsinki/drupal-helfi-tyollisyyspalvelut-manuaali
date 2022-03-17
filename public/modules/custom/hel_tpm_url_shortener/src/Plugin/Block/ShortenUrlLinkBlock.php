<?php

namespace Drupal\hel_tpm_url_shortener\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides url shortener link block.
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
    return \Drupal::formBuilder()->getForm('Drupal\hel_tpm_url_shortener\Form\ShortenUrlLinkForm');
  }

}
