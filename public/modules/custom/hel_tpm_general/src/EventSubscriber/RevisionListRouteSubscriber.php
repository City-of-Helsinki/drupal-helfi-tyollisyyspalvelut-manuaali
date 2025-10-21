<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscribes to route alteration events to modify revision list routes.
 */
final class RevisionListRouteSubscriber extends RouteSubscriberBase implements EventSubscriberInterface {

  /**
   * Constructs a RevisionListRouteSubscriber object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($collection->all() as $route_name => $route) {
      if ($route_name === 'entity.node.version_history') {
        $route->setRequirement('_custom_access', 'Drupal\hel_tpm_general\RevisionListAccessController::access');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();

    // Use a lower priority than \Drupal\views\EventSubscriber\RouteSubscriber
    // to ensure the requirement will be added to its routes.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -300];

    return $events;
  }

}
