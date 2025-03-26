<?php

namespace Drupal\hel_tpm_migrate_tools\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Generate a paragraph.
 *
 * @MigrateProcessPlugin(
 *   id = "tpm_content_paragraph"
 * )
 */
class TPMContentParagraph extends ParagraphBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrateExecutable, Row $row, $destinationProperty) {
    $paragraphs = [];

    if (!is_array($value)) {
      $paragraphs[] = $this->createContentParagraph($row, $destinationProperty, NULL, $value);
    }
    else {
      foreach ($value as $key => $body_val) {
        if (!empty($body_val)) {
          $paragraphs[] = $this->createContentParagraph(
            $row,
            $destinationProperty,
            $this->getConfigValues($row, 'titles')[$key],
            $body_val
          );
        }
      }
    }
    return $paragraphs;
  }

  /**
   * Create a content paragraph with specified content.
   *
   * @param Drupal\migrate\Row $row
   *   The row being handled.
   * @param string $destinationProperty
   *   The property we're currently handling.
   * @param string|null $title
   *   The title of the paragraph.
   * @param string $body
   *   The body of the paragraph.
   *
   * @return array
   *   The paragraph's IDs as necessary to include in a entity reference field.
   */
  private function createContentParagraph($row, $destinationProperty, $title, $body) {
    $paragraph = $this->createParagraph($row, $destinationProperty, 'content', 0);
    if (!empty($title)) {
      $paragraph->field_title = $title;
    }
    $paragraph->field_body = $body;
    $paragraph->save();

    return [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
  }

}
