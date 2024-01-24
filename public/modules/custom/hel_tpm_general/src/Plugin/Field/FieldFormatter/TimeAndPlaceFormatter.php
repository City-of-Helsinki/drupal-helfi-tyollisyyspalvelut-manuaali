<?php declare(strict_types = 1);

namespace Drupal\hel_tpm_general\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'Time and Place formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "hel_tpm_general_time_and_place_formatter",
 *   label = @Translation("Time and Place formatter"),
 *   field_types = {"entity_reference_revisions"},
 * )
 */
final class TimeAndPlaceFormatter extends EntityReferenceEntityFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {

      if (!$entity->hasField('field_service_location')) {
        continue;
      }
      if (!$entity->hasField('field_dates')) {
        continue;
      }
      if ($entity->get('field_service_location')->isEmpty() && $entity->get('field_dates')->isEmpty())  {
        $items->removeItem($delta);
      }
    }
    return parent::viewElements($items, $langcode);
  }

}
