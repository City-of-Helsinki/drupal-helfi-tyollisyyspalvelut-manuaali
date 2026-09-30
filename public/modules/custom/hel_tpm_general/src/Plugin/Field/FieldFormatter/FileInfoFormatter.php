<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Bytes;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mime\MimeTypes;

/**
 * Plugin implementation of the 'File info' formatter.
 *
 * Renders the file description (or file name), file format and size together
 * with a download link. PDF and image files also get a link for opening the
 * file in a new browser tab.
 *
 * The texts are shown in the interface language, or always in the language
 * selected in the formatter settings, e.g. on pages that are only in Finnish.
 */
#[FieldFormatter(
  id: 'hel_tpm_file_info',
  label: new TranslatableMarkup('File info with download link'),
  field_types: [
    'file',
  ],
)]
final class FileInfoFormatter extends FileFormatterBase {

  /**
   * MIME types of files that can be opened in a new browser tab.
   *
   * SVG images are left out, because they can contain scripts.
   */
  private const BROWSER_MIME_TYPES = [
    'application/pdf',
    'image/gif',
    'image/jpeg',
    'image/png',
    'image/webp',
  ];

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'langcode' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $options = ['' => $this->t('Interface language')];
    foreach ($this->languageManager->getLanguages() as $language) {
      $options[$language->getId()] = $language->getName();
    }

    $elements['langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#description' => $this->t('The language of the texts, such as the download link and the file size unit.'),
      '#default_value' => $this->getSetting('langcode'),
      '#options' => $options,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $langcode = $this->getSetting('langcode');
    $language = $langcode ? $this->languageManager->getLanguage($langcode) : NULL;
    $summary[] = $this->t('Language: @language', [
      '@language' => $langcode ? ($language ? $language->getName() : $langcode) : $this->t('Interface language'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    // Language of the texts. NULL uses the interface language.
    $text_langcode = $this->getSetting('langcode') ?: NULL;

    /** @var \Drupal\file\FileInterface $file */
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $item = $file->_referringItem;
      $description = trim((string) ($item->description ?? ''));
      $filename = $file->getFilename();

      $elements[$delta] = [
        '#theme' => 'hel_tpm_general_file_info',
        '#title' => $description !== '' ? $description : $filename,
        '#filename' => $filename,
        '#extension' => $this->fileType($file),
        '#size' => $this->formatSize((int) $file->getSize(), $text_langcode),
        '#url' => $file->createFileUrl(),
        '#opens_in_browser' => in_array($file->getMimeType(), self::BROWSER_MIME_TYPES, TRUE),
        '#langcode' => $text_langcode,
        '#cache' => [
          'tags' => $file->getCacheTags(),
        ],
      ];
    }

    return $elements;
  }

  /**
   * Gets the file type shown to the user, e.g. "PDF".
   *
   * The type comes from the file's MIME type, so that e.g. both .jpg and .jpeg
   * files are shown as "JPG". No type is shown when the MIME type is unknown,
   * because the file name alone does not tell the type reliably.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file.
   *
   * @return string
   *   The file type in upper case, or an empty string.
   */
  private function fileType(FileInterface $file): string {
    $mime_type = (string) $file->getMimeType();
    if ($mime_type === '' || $mime_type === 'application/octet-stream') {
      return '';
    }
    $extensions = MimeTypes::getDefault()->getExtensions($mime_type);
    return mb_strtoupper($extensions[0] ?? '');
  }

  /**
   * Formats a file size with one decimal, e.g. "1,2 Mt" in Finnish.
   *
   * Core's ByteSizeMarkup uses two decimals and a decimal point in every
   * language, so the size is formatted here instead.
   *
   * @param int $size
   *   File size in bytes.
   * @param string|null $langcode
   *   Language of the size, or NULL for the interface language.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The formatted size.
   */
  private function formatSize(int $size, ?string $langcode = NULL): TranslatableMarkup {
    $langcode ??= $this->languageManager->getCurrentLanguage()->getId();
    $options = ['context' => 'File size', 'langcode' => $langcode];
    if ($size < Bytes::KILOBYTE) {
      return $this->t('@size B', ['@size' => $size], $options);
    }

    // Kilobytes, megabytes or gigabytes.
    $value = $size / Bytes::KILOBYTE;
    $unit = 0;
    while (round($value, 1) >= Bytes::KILOBYTE && $unit < 2) {
      $value /= Bytes::KILOBYTE;
      $unit++;
    }

    $decimal_separator = in_array($langcode, ['fi', 'sv'], TRUE) ? ',' : '.';
    $value = round($value, 1);
    $args = ['@size' => number_format($value, floor($value) == $value ? 0 : 1, $decimal_separator, '')];

    return match ($unit) {
      0 => $this->t('@size kB', $args, $options),
      1 => $this->t('@size MB', $args, $options),
      default => $this->t('@size GB', $args, $options),
    };
  }

}
