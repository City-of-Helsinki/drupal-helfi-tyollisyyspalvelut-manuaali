<?php

namespace Drupal\hel_tpm_service_stats\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * A handler to provide service publish intervals.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("hel_tpm_service_stats_publish_interval")
 */
class HelTpmServiceStatsPublishInterval extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    return $values->_entity->publish_interval->getValue()[0]['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing.
  }

}
