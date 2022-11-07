<?php

namespace Drupal\hel_tpm_migrate_tools\Plugin\migrate\process;

use Drupal\hel_tpm_migrate_tools\Plugin\migrate\process\ParagraphBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Component\Utility\Html;

/**
 * Generate a paragraph.
 *
 * @MigrateProcessPlugin(
 *   id = "tpm_text_with_toolbar_paragraph"
 * )
 */
class TPMTextWithToolbarParagraph extends ParagraphBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrateExecutable, Row $row, $destinationProperty) {
    $paragraph = $this->createParagraph($row, $destinationProperty, 'long_text_field_with_toolbar', 0);
    $paragraph->field_description = $value;
    $paragraph->save();

    return [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
  }

}
