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
    return [
      BuildSearchParamsEvent::BUILD_QUERY => 'buildSearchParamsEvent',
    ];
  }

  /**
   * React to an ES search query being prepared.
   *
   * @param \Drupal\elasticsearch_connector\Event\PrepareSearchQueryEvent $event
   *   ES search query prepare event.
   */
  public function buildSearchParamsEvent(BuildSearchParamsEvent $event) {
    $q = $event->getElasticSearchParams();

    $query = $q['body'];

    $q['body'] = [
      'query' => [
        'function_score' => [
          'query' => $query['query'],
          'boost_mode' => 'sum',
          'max_boost' => 10,
          'min_score' => 0,
          'functions' => [
            [
              'field_value_factor' => [
                'field' => 'hel_tpm_priority_boost',
                'factor' => 0.00033,
                'missing' => 1,
              ],
            ],
          ],
        ],
      ],
    ];

    $event->setElasticSearchParams($q);
  }

}
