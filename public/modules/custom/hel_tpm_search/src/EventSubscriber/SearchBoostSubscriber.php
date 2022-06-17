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
          'functions' => [
            [
              'field_value_factor' => [
                'field' => 'hel_tpm_priority_boost',
                'factor' => 1.5,
                'missing' => 1,
                'modifier' => "none"
              ],
            ]
          ]
        ]
      ]
    ];

    $event->setElasticSearchParams($q);
  }

}
