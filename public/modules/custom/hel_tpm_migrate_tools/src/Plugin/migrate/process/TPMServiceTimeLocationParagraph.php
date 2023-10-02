<?php

namespace Drupal\hel_tpm_migrate_tools\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Generate a paragraph.
 *
 * @MigrateProcessPlugin(
 *   id = "tpm_service_time_location_paragraph"
 * )
 */
class TPMServiceTimeLocationParagraph extends ParagraphBase {

  const LANG_REGEXP = '/([a-zA-Z-]+)(\:\s([A-Za-z0-9\.]+))?([\;\,]|$)/';

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrateExecutable, Row $row, $destinationProperty) {
    // Handle the value.
    $language = $this->getConfigValues($row, 'language');
    preg_match_all(self::LANG_REGEXP, $language, $matches);
    $languages = [];

    foreach ($matches[1] as $key => $val) {
      if (empty($val)) {
        continue;
      }
      $lang = [
        'language' => $val,
      ];

      if (!empty($matches[3][$key])) {
        $lang['level'] = $matches[3][$key];
      }
      $languages[] = $lang;
    }

    if (!empty($languages)) {
      $paragraphs = [];
      $paragraph = $this->createParagraph($row, $destinationProperty, 'service_time_and_place', 0);

      foreach ($languages as $language) {
        $language_paragraph = $this->createParagraph($row, $destinationProperty, 'service_language', 0);
        $language_paragraph->field_language = $this->getTidByName($language['language'], 'service_languages');
        if (!empty($language['level'])) {
          $language_paragraph->field_level = $this->getTidByName($language['level'], 'language_level');
        }
        $language_paragraph->save();
        $paragraph->field_service_languages[] = [
          'target_id' => $language_paragraph->id(),
          'target_revision_id' => $language_paragraph->getRevisionId(),
        ];
      }

      $paragraph->save();
      $paragraphs[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
      return $paragraphs;
    }
  }

}
