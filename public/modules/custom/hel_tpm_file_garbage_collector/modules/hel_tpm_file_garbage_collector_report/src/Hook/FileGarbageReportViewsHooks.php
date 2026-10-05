<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector_report\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hel_tpm_file_garbage_collector_report\FileGarbageReport;

/**
 * Views hook implementations for File garbage collector report module.
 */
final class FileGarbageReportViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $table = FileGarbageReport::TABLE;
    $data[$table]['table']['group'] = $this->t('File garbage collector');
    $data[$table]['table']['base'] = [
      'field' => 'fid',
      'title' => $this->t('File garbage collector report'),
      'help' => $this->t('Files deleted, or selected for deletion in dry run mode, by the file garbage collector.'),
    ];

    $data[$table]['fid'] = [
      'title' => $this->t('File ID'),
      'help' => $this->t('The ID of the reported file.'),
      'field' => ['id' => 'numeric'],
      'filter' => ['id' => 'numeric'],
      'argument' => ['id' => 'numeric'],
      'sort' => ['id' => 'standard'],
    ];
    $data[$table]['status'] = [
      'title' => $this->t('Status'),
      'help' => $this->t('Whether the file would be deleted (dry run) or was deleted.'),
      'field' => [
        'id' => 'machine_name',
        'options callback' => FileGarbageReport::class . '::getStatusLabels',
      ],
      'filter' => [
        'id' => 'in_operator',
        'options callback' => FileGarbageReport::class . '::getStatusLabels',
      ],
      'argument' => ['id' => 'string'],
      'sort' => ['id' => 'standard'],
    ];
    foreach ([
      'filename' => [$this->t('File name'), $this->t('The name of the file.')],
      'uri' => [$this->t('URI'), $this->t('The URI of the file.')],
      'filemime' => [$this->t('MIME type'), $this->t('The MIME type of the file.')],
    ] as $field => [$title, $help]) {
      $data[$table][$field] = [
        'title' => $title,
        'help' => $help,
        'field' => ['id' => 'standard'],
        'filter' => ['id' => 'string'],
        'argument' => ['id' => 'string'],
        'sort' => ['id' => 'standard'],
      ];
    }
    $data[$table]['filesize'] = [
      'title' => $this->t('File size'),
      'help' => $this->t('The size of the file.'),
      'field' => ['id' => 'file_size'],
      'filter' => ['id' => 'numeric'],
      'sort' => ['id' => 'standard'],
    ];
    $data[$table]['file_owner'] = [
      'title' => $this->t('File owner'),
      'help' => $this->t('The user who uploaded the file.'),
      'field' => ['id' => 'numeric'],
      'filter' => ['id' => 'numeric'],
      'argument' => ['id' => 'numeric'],
      'relationship' => [
        'title' => $this->t('File owner'),
        'help' => $this->t('The user who uploaded the file.'),
        'base' => 'users_field_data',
        'base field' => 'uid',
        'id' => 'standard',
        'label' => $this->t('File owner'),
      ],
    ];
    $data[$table]['usages'] = [
      'title' => $this->t('Used in'),
      'help' => $this->t('The entities and revisions that used the file.'),
      'field' => [
        'id' => 'hel_tpm_file_garbage_collector_report_usages',
        'click sortable' => FALSE,
      ],
    ];
    $data[$table]['references_removed'] = [
      'title' => $this->t('Revision values removed'),
      'help' => $this->t('The number of old revision field values removed with the file.'),
      'field' => ['id' => 'numeric'],
      'filter' => ['id' => 'numeric'],
      'sort' => ['id' => 'standard'],
    ];
    foreach ([
      'file_created' => [$this->t('Uploaded'), $this->t('When the file was uploaded.')],
      'last_used' => [$this->t('Last used'), $this->t('When the file was removed from the content.')],
      'created' => [$this->t('First reported'), $this->t('When the file was first reported.')],
      'changed' => [$this->t('Reported'), $this->t('When the report entry was last updated.')],
    ] as $field => [$title, $help]) {
      $data[$table][$field] = [
        'title' => $title,
        'help' => $help,
        'field' => ['id' => 'date'],
        'filter' => ['id' => 'date'],
        'argument' => ['id' => 'date'],
        'sort' => ['id' => 'date'],
      ];
    }

    $data['views']['hel_tpm_file_garbage_collector_report_summary'] = [
      'title' => $this->t('File garbage collector summary'),
      'help' => $this->t('Shows the current mode and the number of reported files.'),
      'area' => ['id' => 'hel_tpm_file_garbage_collector_report_summary'],
    ];
    return $data;
  }

}
