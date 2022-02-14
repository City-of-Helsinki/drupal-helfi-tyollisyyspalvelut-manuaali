<?php

namespace Drupal\hel_tpm_url_shortener\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Hel TPM Url shortener routes.
 */
class HelTpmUrlShortenerController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
