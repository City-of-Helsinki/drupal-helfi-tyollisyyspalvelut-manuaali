<?php
namespace Drupal\service_manual_workflow\Access;

use Drupal\Core\Access\AccessResult;

class ServiceOutdatedAccess {
  public function access() {
    return AccessResult::allowed();
  }
}