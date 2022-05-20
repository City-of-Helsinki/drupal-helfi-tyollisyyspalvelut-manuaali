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
 *   id = "tpm_target_group_paragraph"
 * )
 */
class TPMTargetGroupParagraph extends ParagraphBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrateExecutable, Row $row, $destinationProperty) {
    $paragraphs = [];

    $paragraph = $this->createParagraph($row, $destinationProperty, 'target_group', 0);
    $paragraph->field_description = $value;
    $paragraph->save();
    $paragraphs[] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
    return $paragraphs;
  }

}
