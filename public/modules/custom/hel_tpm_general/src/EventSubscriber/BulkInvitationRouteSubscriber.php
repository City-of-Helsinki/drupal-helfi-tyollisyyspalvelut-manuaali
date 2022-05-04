<?php

namespace Drupal\hel_tpm_general\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Helsinki TPM General route subscriber.
 */
class BulkInvitationRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $this->alterGinviteInvitationBulkRoute($collection);
    $this->alterGinviteInvitationBulkConfirmRoute($collection);
  }

  private function alterGinviteInvitationBulkRoute(RouteCollection $collection) {
    $route = $collection->get('ginvite.invitation.bulk');
    $route->setDefault('_form', 'Drupal\hel_tpm_general\Form\BulkGroupInvitationRoles');
  }

  private function alterGinviteInvitationBulkConfirmRoute(RouteCollection $collection) {
    $route = $collection->get('ginvite.invitation.bulk.confirm');
    $route->setDefault('_form', 'Drupal\hel_tpm_general\Form\BulkGroupInvitationRolesConfirm');
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
