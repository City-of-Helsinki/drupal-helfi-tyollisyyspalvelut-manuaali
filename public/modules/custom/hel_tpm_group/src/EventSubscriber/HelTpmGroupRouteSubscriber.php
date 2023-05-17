<?php

namespace Drupal\hel_tpm_group\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * hel_tpm_group route subscriber.
 */
class HelTpmGroupRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($collection->all() as $route_name => $route) {
      // Prevent group admins from adding existing content to groups.
      // Group 1 has bug which requires group users to have relate existing content permissions in order to be able
      // add new content to group. Bug should be fixed in Group 2.x but upgrading brings out other issues so this
      // is a bubble gum fix to circumvent the issue ordinary users being able to hijack editing permissions from other
      // group content.
      // @todo When upgrading to Group 2.0 check it this is needed anymore.
      if ($route_name === 'entity.group_content.group_node_relate_page') {
        $route->setRequirement('_permission', 'bypass group access');
      }

      if ($route_name === 'entity.group_content.add_form') {
        $route->setRequirement('_permission', 'administer users');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();

    // Use a lower priority than \Drupal\views\EventSubscriber\RouteSubscriber
    // to ensure the requirement will be added to its routes.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -300];

    return $events;
  }

}
