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
    $paragraph->field_description = $this->getConfigValues($row, 'description');
    $paragraph->field_municipality = $this->getTidByName($this->getConfigValues($row, 'municipality'), 'municipality');

    switch ($this->getConfigValues($row, 'age')) {
      case 'Ei ikään liittyviä rajoituksia':
      case 'Ei ikään liittyvää rajoitusta':
        $paragraph->field_age_groups = 'no_age_restriction';
        break;
      case 'Alle 30-vuotiaat':
        $paragraph->field_age_groups = 'under_30';
        break;
      case '30-vuotiaat tai vanhemmat':
        $paragraph->field_age_groups = 'over_30';
        break;
      case 'Yli 55-vuotiaat':
        $paragraph->field_age_groups = 'over_57';
        break;
    }

    $paragraph->save();
    $paragraphs[] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
    return $paragraphs;
  }

}
