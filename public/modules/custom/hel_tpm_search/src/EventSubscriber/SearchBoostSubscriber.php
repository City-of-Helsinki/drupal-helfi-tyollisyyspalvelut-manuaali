<?php

namespace Drupal\hel_tpm_search\EventSubscriber;

use Drupal\elasticsearch_connector\Event\BuildSearchParamsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class ElasticSearchBoostSubscriber.
 *
 * @package Drupal\hel_tpm_search\EventSubscriber
 */
class SearchBoostSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents() {
    return [];
  }

  /**
   * React to an ES search query being prepared.
   *
   * @param \Drupal\elasticsearch_connector\Event\PrepareSearchQueryEvent $event
   *   ES search query prepare event.
   */
  public function buildSearchParamsEvent(BuildSearchParamsEvent $event) {
  }

}
