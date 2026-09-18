<?php

namespace Drupal\hel_tpm_better_locale_interface\Controller;

use Drupal\hel_tpm_better_locale_interface\Form\BetterTranslateEditForm;
use Drupal\hel_tpm_better_locale_interface\Form\BetterTranslateFilterForm;
use Drupal\locale\Controller\LocaleController;

/**
 * Enhanced translation controller.
 */
class BetterUiTranslationController extends LocaleController {

  /**
   * {@inheritdoc}
   */
  public function translatePage() {
    return [
      'filter' => $this->formBuilder()->getForm(BetterTranslateFilterForm::class),
      'form' => $this->formBuilder()->getForm(BetterTranslateEditForm::class),
    ];
  }

}
