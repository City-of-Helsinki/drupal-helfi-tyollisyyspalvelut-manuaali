<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_general\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the hel_tpm_file_info field formatter.
 *
 * @group hel_tpm_general
 */
final class FileInfoFormatterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'entity_test',
    'purge',
    'entity',
    'flexible_permissions',
    'options',
    'group',
    'hel_tpm_general',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);

    FieldStorageConfig::create([
      'field_name' => 'field_files',
      'entity_type' => 'entity_test',
      'type' => 'file',
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_files',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' => [
        'description_field' => TRUE,
        'file_extensions' => 'pdf docx',
      ],
    ])->save();
  }

  /**
   * Tests rendering of file name, description, format, size and actions.
   */
  public function testFileInfoOutput(): void {
    $pdf = $this->createFile('guide.pdf', 'application/pdf', 2048);
    $docx = $this->createFile('form.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 500);

    $entity = EntityTest::create([
      'field_files' => [
        ['target_id' => $pdf->id(), 'description' => 'Service guide', 'display' => TRUE],
        ['target_id' => $docx->id(), 'description' => '', 'display' => TRUE],
      ],
    ]);
    $entity->save();

    $build = $entity->get('field_files')->view(['type' => 'hel_tpm_file_info']);
    $this->render($build);

    $items = $this->cssSelect('.file-info');
    $this->assertCount(2, $items);

    // The description replaces the file name when it is given.
    $this->assertSame('Service guide', trim((string) $items[0]->xpath('.//*[@class="file-info__name"]')[0]));
    $this->assertSame('PDF', trim((string) $items[0]->xpath('.//*[@class="file-info__type"]')[0]));
    $this->assertSame('2 kB', trim((string) $items[0]->xpath('.//*[@class="file-info__size"]')[0]));
    $download = $items[0]->xpath('.//a[contains(@class, "file-info__action--download")]');
    $this->assertCount(1, $download);
    $this->assertSame('guide.pdf', (string) $download[0]['download']);
    $open = $items[0]->xpath('.//a[contains(@class, "file-info__action--open")]');
    $this->assertCount(1, $open);
    $this->assertSame('_blank', (string) $open[0]['target']);

    // Without a description the file name is shown, and non-PDF files can only
    // be downloaded.
    $this->assertSame('form.docx', trim((string) $items[1]->xpath('.//*[@class="file-info__name"]')[0]));
    $this->assertSame('DOCX', trim((string) $items[1]->xpath('.//*[@class="file-info__type"]')[0]));
    $this->assertSame('500 B', trim((string) $items[1]->xpath('.//*[@class="file-info__size"]')[0]));
    $this->assertCount(1, $items[1]->xpath('.//a[contains(@class, "file-info__action--download")]'));
    $this->assertCount(0, $items[1]->xpath('.//a[contains(@class, "file-info__action--open")]'));
  }

  /**
   * Tests the file type and which files can be opened in the browser.
   */
  public function testFileTypeAndBrowserLink(): void {
    $cases = [
      // File name, MIME type, expected type, opens in browser.
      ['photo.jpeg', 'image/jpeg', 'JPG', TRUE],
      ['chart.png', 'image/png', 'PNG', TRUE],
      ['drawing.svg', 'image/svg+xml', 'SVG', FALSE],
      ['notes.doc', 'application/msword', 'DOC', FALSE],
      ['unknown.pdf', 'application/octet-stream', '', FALSE],
      ['unknown.xyz', 'application/unknown', '', FALSE],
    ];
    foreach ($cases as [$filename, $mime, $type, $opens_in_browser]) {
      $file = $this->createFile($filename, $mime, 100);
      $entity = EntityTest::create([
        'field_files' => [['target_id' => $file->id(), 'display' => TRUE]],
      ]);
      $entity->save();

      $build = $entity->get('field_files')->view(['type' => 'hel_tpm_file_info']);
      $this->render($build);
      $type_element = $this->cssSelect('.file-info__type');
      if ($type === '') {
        $this->assertCount(0, $type_element, $filename);
      }
      else {
        $this->assertSame($type, trim((string) $type_element[0]), $filename);
      }
      $this->assertCount($opens_in_browser ? 1 : 0, $this->cssSelect('.file-info__action--open'), $filename);
    }
  }

  /**
   * Tests the file size format.
   */
  public function testFileSizeFormat(): void {
    $sizes = [
      1258291 => '1.2 MB',
      4300 => '4.2 kB',
      1048575 => '1 MB',
      3 * 1024 * 1024 * 1024 => '3 GB',
    ];
    foreach ($sizes as $size => $expected) {
      $file = $this->createFile("file-$size.pdf", 'application/pdf', $size);
      $entity = EntityTest::create([
        'field_files' => [['target_id' => $file->id(), 'display' => TRUE]],
      ]);
      $entity->save();

      $build = $entity->get('field_files')->view(['type' => 'hel_tpm_file_info']);
      $this->render($build);
      $this->assertSame($expected, trim((string) $this->cssSelect('.file-info__size')[0]), "Size $size");
    }
  }

  /**
   * Tests that the language setting is used for the texts.
   */
  public function testLanguageSetting(): void {
    $file = $this->createFile('guide.pdf', 'application/pdf', 4300);
    $entity = EntityTest::create([
      'field_files' => [['target_id' => $file->id(), 'display' => TRUE]],
    ]);
    $entity->save();

    // The interface language (English) is used by default.
    $build = $entity->get('field_files')->view(['type' => 'hel_tpm_file_info']);
    $this->render($build);
    $this->assertSame('4.2 kB', trim((string) $this->cssSelect('.file-info__size')[0]));

    // A selected language is used even if the interface is in another one.
    $build = $entity->get('field_files')->view([
      'type' => 'hel_tpm_file_info',
      'settings' => ['langcode' => 'fi'],
    ]);
    $this->render($build);
    $this->assertSame('4,2 kB', trim((string) $this->cssSelect('.file-info__size')[0]));
  }

  /**
   * Creates a permanent file entity.
   */
  private function createFile(string $filename, string $mime, int $size): FileInterface {
    $file = File::create([
      'uri' => 'public://' . $filename,
      'filename' => $filename,
      'filemime' => $mime,
      'filesize' => $size,
    ]);
    $file->setPermanent();
    $file->save();
    return $file;
  }

}
