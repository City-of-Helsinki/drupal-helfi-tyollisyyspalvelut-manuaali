<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_forms\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Hooks for the service_time_and_place paragraph.
 */
final class ServiceTimeAndPlaceHooks {

  /**
   * Clears the service location unless the "location" channel is selected.
   *
   * The location field is only shown for the "location" channel, so a value
   * left behind after switching the channel must not be saved.
   */
  #[Hook('paragraph_presave')]
  public function paragraphPresave(EntityInterface $entity): void {
    if (!$entity instanceof ParagraphInterface
      || $entity->bundle() !== 'service_time_and_place'
      || !$entity->hasField('field_service_channel')
      || !$entity->hasField('field_service_location')) {
      return;
    }

    if ($entity->get('field_service_channel')->value === 'location') {
      return;
    }

    if (!$entity->get('field_service_location')->isEmpty()) {
      $entity->set('field_service_location', NULL);
    }
  }

}
