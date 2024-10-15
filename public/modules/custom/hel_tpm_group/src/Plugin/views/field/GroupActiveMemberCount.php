<?php

namespace Drupal\hel_tpm_group\Plugin\views\field;

use Drupal\group\Entity\Group;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * A handler to provide the number of active group members.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("hel_tpm_group_active_member_count")
 */
class GroupActiveMemberCount extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $activeMembers = 0;
    if ($values->_entity instanceof Group) {
      $members = $values->_entity->getMembers();
      foreach ($members as $member) {
        if (!$member->getUser()->isBlocked()) {
          $activeMembers++;
        }
      }
    }
    return $activeMembers;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {}

}
