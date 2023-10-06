<?php

namespace Drupal\hel_tpm_group_invite\EventSubscriber;

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

  /**
   * Alter ginvite module to use BulkGroupInvitationCustom form.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   Route collection.
   *
   * @return void
   *   -
   */
  private function alterGinviteInvitationBulkRoute(RouteCollection $collection) {
    $route = $collection->get('ginvite.invitation.bulk');
    if (empty($route)) {
      return;
    }
    $route->setDefault('_form', 'Drupal\hel_tpm_group_invite\Form\BulkGroupInvitationCustom');
  }

  /**
   * Alter bulk confirm form to use BulkGroupInvitationCustomConfirm class.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   Route collection.
   *
   * @return void
   *   -
   */
  private function alterGinviteInvitationBulkConfirmRoute(RouteCollection $collection) {
    $route = $collection->get('ginvite.invitation.bulk.confirm');
    if (empty($route)) {
      return;
    }
    $route->setDefault('_form', 'Drupal\hel_tpm_group_invite\Form\BulkGroupInvitationCustomConfirm');
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
