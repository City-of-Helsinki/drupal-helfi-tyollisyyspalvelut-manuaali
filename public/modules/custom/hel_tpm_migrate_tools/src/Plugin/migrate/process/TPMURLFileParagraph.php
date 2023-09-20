<?php

namespace Drupal\hel_tpm_migrate_tools\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

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
        if (empty($match)) {
          continue;
        }
        $url = $match[0];
        if (strpos($match[0], '://') === FALSE) {
          $url = 'https://' . $url;
        }
        $links[]['uri'] = $url;
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
