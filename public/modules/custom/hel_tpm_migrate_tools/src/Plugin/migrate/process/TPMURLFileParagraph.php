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
 *   id = "tpm_url_file_paragraph"
 * )
 */
class TPMURLFileParagraph extends ParagraphBase {

  const URL_REGEXP = '(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})';

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrateExecutable, Row $row, $destinationProperty) {
    preg_match_all(self::URL_REGEXP, $value, $matches);
    if (!empty($matches)) {
      $links = [];
      foreach ($matches as $match) {
        $links[]['uri'] = $match[0];
      }
      if (!empty($links)) {
        $paragraph = $this->createParagraph($row, $destinationProperty, 'url_and_file', 0);
        $paragraph->field_link = $links;
        $paragraph->save();
    
        return [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ];
      }
    }
  }

}
