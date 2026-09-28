<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_better_locale_interface\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber.
 */
final class BetterUiTranslationRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($collection->get('locale.translate_page')) {
      $collection->get('locale.translate_page')->setDefault('_controller', '\Drupal\hel_tpm_better_locale_interface\Controller\BetterUiTranslationController::translatePage');
    }
  }

}
