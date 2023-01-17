<?php

namespace Drupal\hel_tpm_migrate_tools\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Base class for paragraph process plugins.
 */
class ParagraphBase extends ProcessPluginBase {

  /**
   * Create the paragraph.
   *
   * @param \Drupal\migrate\Row $row
   *   The migration row.
   * @param string $field_name
   *   The field name.
   * @param int $delta
   *   The field delta.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The paragraph.
   */
  protected function createParagraph(Row $row, $field_name, $paragraph_type, $delta) {
    $paragraph = Paragraph::create([
      'type' => $paragraph_type,
    ]);
    return $paragraph;
  }

  protected function getConfigValues(Row $row, $settingName) {
    $field_name = $this->configuration[$settingName];
    if (!is_array($field_name)) {
      return $row->getSource()[$field_name];
    }
    else {
      foreach ($field_name as $key => $field) {
        $field_name[$key] = $row->getSource()[$field];
      }
      return $field_name;
    }
  }

  /**
   * Utility: find term by name and vid.
   *
   * @param string $name
   *   Term name.
   * @param string $vid
   *   Term vid.
   *
   * @return int
   *   Term id, or 0 if none.
   */
  protected function getTidByName($name = NULL, $vid = NULL) {
    if (empty($name) || empty($vid)) {
      return 0;
    }
    $properties = [
      'name' => $name,
      'vid' => $vid,
    ];
    $terms = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
    $term = reset($terms);
    return !empty($term) ? $term->id() : 0;
  }

}
