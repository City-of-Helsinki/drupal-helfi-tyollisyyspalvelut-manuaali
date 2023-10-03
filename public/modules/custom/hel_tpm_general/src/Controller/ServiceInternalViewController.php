<?php

namespace Drupal\hel_tpm_general\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Controller\NodeViewController;

/**
 * Returns responses for Helsinki TPM General routes.
 */
class ServiceInternalViewController extends NodeViewController {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $node, $view_mode = 'full', $langcode = NULL) {
    $view_mode = 'internal';
    return parent::view($node, $view_mode, $langcode);
  }

}
