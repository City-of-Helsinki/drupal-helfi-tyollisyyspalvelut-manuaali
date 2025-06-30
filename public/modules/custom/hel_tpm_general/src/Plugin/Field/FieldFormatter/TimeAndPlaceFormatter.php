<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter\EntityReferenceRevisionsEntityFormatter;

/**
 * Plugin implementation of the 'Time and Place formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "hel_tpm_general_time_and_place_formatter",
 *   label = @Translation("Time and Place formatter"),
 *   field_types = {"entity_reference_revisions"},
 * )
 */
final class TimeAndPlaceFormatter extends EntityReferenceRevisionsEntityFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $deltasToRemove = [];
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      if (!$entity->hasField('field_service_location') || !$entity->hasField('field_date_selection')) {
        continue;
      }
      if ($entity->get('field_service_location')->isEmpty() && $entity->get('field_date_selection')->isEmpty()) {
        $deltasToRemove[] = $delta;
      }
    }

    // Hide empty items by removing them in reverse order to ensure the removed
    // index exists.
    rsort($deltasToRemove);
    foreach ($deltasToRemove as $delta) {
      $items->removeItem($delta);
    }

    return parent::viewElements($items, $langcode);
  }

}
