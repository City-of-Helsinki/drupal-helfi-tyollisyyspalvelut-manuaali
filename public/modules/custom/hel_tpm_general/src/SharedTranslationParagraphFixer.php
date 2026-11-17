<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Splits paragraphs shared between translations of translatable fields.
 *
 * A translatable paragraphs field holds asymmetric translations: every host
 * translation must reference its own paragraph entities. Content translated
 * before the field became translatable can still share paragraph entities
 * between translations. Saving one translation then creates a new default
 * revision of the shared paragraphs, leaving the other translations pointing
 * to an old default revision, and saving those translations fails with
 * "An existing default revision of the 'paragraph' entity type can not be
 * changed to a non-default revision".
 *
 * The default translation keeps the original paragraphs, re-saved as the
 * default revision from the revision it references. Every other translation
 * gets single-language copies of the paragraph revisions it references, so
 * the visible content of each translation stays the same.
 */
class SharedTranslationParagraphFixer {

  /**
   * Field and paragraph keys that must not be copied to duplicates.
   */
  protected const SKIPPED_KEYS = [
    'id',
    'uuid',
    'revision_id',
    'langcode',
    'default_langcode',
    'revision_default',
    'revision_translation_affected',
    'content_translation_source',
    'content_translation_outdated',
    'parent_id',
    'parent_type',
    'parent_field_name',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected Connection $database,
  ) {}

  /**
   * Returns IDs of host entities sharing paragraphs between translations.
   *
   * @param string $entity_type_id
   *   The host entity type ID.
   *
   * @return int[]
   *   Host entity IDs, sorted ascending.
   */
  public function findAffectedEntityIds(string $entity_type_id = 'node'): array {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if (!$storage instanceof SqlEntityStorageInterface) {
      return [];
    }
    $table_mapping = $storage->getTableMapping();

    $ids = [];
    foreach ($this->getParagraphFieldStorages($entity_type_id) as $field_storage) {
      $table = $table_mapping->getDedicatedDataTableName($field_storage);
      $target_column = $table_mapping->getFieldColumnName($field_storage, 'target_id');

      $query = $this->database->select($table, 'f');
      $query->addField('f', 'entity_id');
      $query->groupBy('f.entity_id');
      $query->groupBy('f.' . $target_column);
      $query->having('COUNT(DISTINCT f.langcode) > 1');
      foreach ($query->execute()->fetchCol() as $id) {
        $ids[(int) $id] = (int) $id;
      }
    }
    ksort($ids);
    return array_values($ids);
  }

  /**
   * Splits shared paragraphs of a host entity into per-translation copies.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The host entity (default revision).
   *
   * @return int
   *   The number of paragraph references that were replaced or re-saved.
   */
  public function fixEntity(ContentEntityInterface $entity): int {
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $entity = $entity->getUntranslated();
    $default_langcode = $entity->language()->getId();
    $changes = 0;

    foreach ($this->getParagraphFieldStorages($entity->getEntityTypeId()) as $field_name => $field_storage) {
      if (!$entity->hasField($field_name) || !$entity->getFieldDefinition($field_name)->isTranslatable()) {
        continue;
      }

      // Paragraph IDs referenced by more than one translation.
      $usage = [];
      foreach ($entity->getTranslationLanguages() as $langcode => $language) {
        foreach ($entity->getTranslation($langcode)->get($field_name) as $item) {
          $usage[$item->target_id][$langcode] = TRUE;
        }
      }
      $shared = array_keys(array_filter($usage, static fn(array $langcodes): bool => count($langcodes) > 1));
      if (!$shared) {
        continue;
      }

      foreach ($entity->getTranslationLanguages() as $langcode => $language) {
        $translation = $entity->getTranslation($langcode);
        $values = [];
        $changed = FALSE;

        foreach ($translation->get($field_name) as $item) {
          $value = [
            'target_id' => $item->target_id,
            'target_revision_id' => $item->target_revision_id,
          ];
          $paragraph = $paragraph_storage->loadRevision($item->target_revision_id);

          if ($paragraph instanceof ParagraphInterface) {
            if ($langcode !== $default_langcode && in_array($item->target_id, $shared)) {
              // Other translations get their own copy of the revision they
              // currently show.
              $value = ['entity' => $this->createSingleLanguageDuplicate($paragraph, $langcode)];
              $changed = TRUE;
            }
            elseif (!$paragraph->isDefaultRevision()) {
              // The default translation keeps the paragraph, restoring the
              // referenced revision as the default one.
              $paragraph->setNewRevision(TRUE);
              $paragraph->isDefaultRevision(TRUE);
              $paragraph->save();
              $value['target_revision_id'] = $paragraph->getRevisionId();
              $changed = TRUE;
            }
          }
          $values[] = $value;
        }

        if ($changed) {
          $translation->set($field_name, $values);
          $changes++;
        }
      }
    }

    if ($changes) {
      $entity->setNewRevision(TRUE);
      if (method_exists($entity, 'setRevisionLogMessage')) {
        $entity->setRevisionLogMessage('Separated paragraphs shared between translations.');
      }
      $entity->setSyncing(TRUE);
      $entity->save();
    }

    return $changes;
  }

  /**
   * Creates an unsaved, single-language copy of a paragraph revision.
   *
   * Nested paragraphs are copied recursively.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph revision to copy.
   * @param string $langcode
   *   The language of the copy.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The new paragraph.
   */
  protected function createSingleLanguageDuplicate(ParagraphInterface $paragraph, string $langcode): ParagraphInterface {
    $source = $paragraph->hasTranslation($langcode) ? $paragraph->getTranslation($langcode) : $paragraph->getUntranslated();
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

    $values = [];
    foreach ($source->getFields(FALSE) as $name => $field) {
      if (in_array($name, static::SKIPPED_KEYS, TRUE)) {
        continue;
      }
      $definition = $field->getFieldDefinition();
      if ($definition->getType() === 'entity_reference_revisions' && $definition->getSetting('target_type') === 'paragraph') {
        $values[$name] = [];
        foreach ($field as $item) {
          $child = $item->target_revision_id ? $paragraph_storage->loadRevision($item->target_revision_id) : NULL;
          if ($child instanceof ParagraphInterface) {
            $values[$name][] = ['entity' => $this->createSingleLanguageDuplicate($child, $langcode)];
          }
        }
        continue;
      }
      $values[$name] = $field->getValue();
    }
    $values['langcode'] = $langcode;

    /** @var \Drupal\paragraphs\ParagraphInterface $duplicate */
    $duplicate = $paragraph_storage->create($values);
    return $duplicate;
  }

  /**
   * Returns paragraph reference field storages of an entity type.
   *
   * @param string $entity_type_id
   *   The host entity type ID.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface[]
   *   Field storage definitions keyed by field name.
   */
  protected function getParagraphFieldStorages(string $entity_type_id): array {
    return array_filter(
      $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id),
      static fn(FieldStorageDefinitionInterface $storage): bool => $storage->getType() === 'entity_reference_revisions'
        && $storage->getSetting('target_type') === 'paragraph'
        && !$storage->isBaseField(),
    );
  }

}
