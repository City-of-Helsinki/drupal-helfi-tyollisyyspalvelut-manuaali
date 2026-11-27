<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_general\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\hel_tpm_general\SharedTranslationParagraphFixer;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests splitting paragraphs shared between translations.
 */
#[CoversClass(SharedTranslationParagraphFixer::class)]
#[Group('hel_tpm_general')]
#[RunTestsInSeparateProcesses]
final class SharedTranslationParagraphFixerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'node',
    'language',
    'content_translation',
    'entity_reference_revisions',
    'paragraphs',
  ];

  /**
   * The fixer under test.
   */
  protected SharedTranslationParagraphFixer $fixer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['language']);

    ConfigurableLanguage::createFromLangcode('fi')->save();
    ConfigurableLanguage::createFromLangcode('sv')->save();

    NodeType::create(['type' => 'basic_page', 'name' => 'Basic page'])->save();
    ParagraphsType::create(['id' => 'text', 'label' => 'Text'])->save();
    ParagraphsType::create(['id' => 'row', 'label' => 'Row'])->save();

    $this->createField('paragraph', 'text', 'field_title', 'string');
    $this->createField('paragraph', 'row', 'field_title', 'string');
    $this->createField('paragraph', 'row', 'field_items', 'entity_reference_revisions', ['target_type' => 'paragraph']);
    $this->createField('node', 'basic_page', 'field_paragraph', 'entity_reference_revisions', ['target_type' => 'paragraph']);

    $translation_manager = $this->container->get('content_translation.manager');
    $translation_manager->setEnabled('node', 'basic_page', TRUE);
    $translation_manager->setEnabled('paragraph', 'text', TRUE);
    $translation_manager->setEnabled('paragraph', 'row', TRUE);
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('entity_type.manager')->clearCachedDefinitions();

    $this->fixer = new SharedTranslationParagraphFixer(
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_field.manager'),
      $this->container->get('database'),
    );
  }

  /**
   * Tests that shared paragraphs are split and translations can be saved.
   */
  public function testFixSharedParagraphs(): void {
    $node = $this->createLegacyNode();
    $nid = (int) $node->id();

    // Reproduce the bug: saving the translation that points to an old
    // revision of the shared paragraphs fails.
    $this->assertSaveFails($this->reloadNode($nid), 'fi');
    $this->assertSame([$nid], $this->fixer->findAffectedEntityIds());

    $this->assertSame(2, $this->fixer->fixEntity($this->reloadNode($nid)));
    $this->assertSame([], $this->fixer->findAffectedEntityIds());

    $node = $this->reloadNode($nid);
    $fi_row = $this->getRow($node, 'fi');
    $sv_row = $this->getRow($node, 'sv');

    // The default translation keeps the original paragraph and the content
    // it showed, now as the default revision.
    $this->assertSame($this->originalRowId, (int) $fi_row->id());
    $this->assertTrue($fi_row->isDefaultRevision());
    $this->assertSame('Rivi fi', $fi_row->getTranslation('fi')->get('field_title')->value);
    $this->assertSame('Teksti fi', $fi_row->getTranslation('fi')->get('field_items')->entity->getTranslation('fi')->get('field_title')->value);

    // The other translation got its own single-language copies of the
    // content it showed, including nested paragraphs.
    $this->assertNotSame((int) $fi_row->id(), (int) $sv_row->id());
    $this->assertSame('sv', $sv_row->language()->getId());
    $this->assertSame([], array_keys($sv_row->getTranslationLanguages(FALSE)));
    $this->assertSame('Rad sv', $sv_row->get('field_title')->value);
    $sv_child = $sv_row->get('field_items')->entity;
    $this->assertInstanceOf(ParagraphInterface::class, $sv_child);
    $this->assertNotSame((int) $fi_row->get('field_items')->target_id, (int) $sv_child->id());
    $this->assertSame('sv', $sv_child->language()->getId());
    $this->assertSame('Text sv', $sv_child->get('field_title')->value);
    $this->assertSame('node', $sv_row->get('parent_type')->value);
    $this->assertSame((string) $nid, (string) $sv_row->get('parent_id')->value);

    // Both translations can now be saved like the paragraphs widget does.
    $this->saveLikeWidget($this->reloadNode($nid), 'fi');
    $this->saveLikeWidget($this->reloadNode($nid), 'sv');
    $this->saveLikeWidget($this->reloadNode($nid), 'fi');

    // Running the fix again does nothing.
    $this->assertSame(0, $this->fixer->fixEntity($this->reloadNode($nid)));
  }

  /**
   * The ID of the row paragraph shared by both translations.
   */
  protected int $originalRowId;

  /**
   * Creates a node whose translations share paragraphs, like legacy content.
   */
  protected function createLegacyNode(): NodeInterface {
    $child = Paragraph::create(['type' => 'text', 'langcode' => 'fi', 'field_title' => 'Teksti fi']);
    $child->addTranslation('sv', ['field_title' => 'Text sv']);
    $row = Paragraph::create([
      'type' => 'row',
      'langcode' => 'fi',
      'field_title' => 'Rivi fi',
      'field_items' => [$child],
    ]);
    $row->addTranslation('sv', ['field_title' => 'Rad sv', 'field_items' => [$child]]);

    $node = Node::create([
      'type' => 'basic_page',
      'langcode' => 'fi',
      'title' => 'Sivu',
      'field_paragraph' => [$row],
    ]);
    $node->save();
    $this->originalRowId = (int) $row->id();

    // Legacy translation referencing the same paragraph entity.
    $node->addTranslation('sv', [
      'title' => 'Sida',
      'field_paragraph' => [
        [
          'target_id' => $row->id(),
          'target_revision_id' => $row->getRevisionId(),
        ],
      ],
    ]);
    $node->setNewRevision(TRUE);
    $node->save();

    // Saving the Swedish translation creates a new default revision of the
    // shared paragraph, leaving the Finnish translation on an old one.
    $this->saveLikeWidget($this->reloadNode((int) $node->id()), 'sv');

    return $this->reloadNode((int) $node->id());
  }

  /**
   * Saves a translation the way the paragraphs widget does.
   *
   * The widget marks every paragraph as needing a save, even when only host
   * fields change.
   */
  protected function saveLikeWidget(NodeInterface $node, string $langcode): void {
    $translation = $node->getTranslation($langcode);
    foreach ($translation->get('field_paragraph') as $item) {
      $item->entity->setNeedsSave(TRUE);
    }
    $translation->setNewRevision(TRUE);
    $translation->save();
  }

  /**
   * Asserts that saving a translation fails with the reported exception.
   */
  protected function assertSaveFails(NodeInterface $node, string $langcode): void {
    try {
      $this->saveLikeWidget($node, $langcode);
      $this->fail('Saving the translation was expected to fail.');
    }
    catch (EntityStorageException $e) {
      $this->assertStringContainsString('can not be changed to a non-default revision', $e->getMessage());
    }
  }

  /**
   * Returns the row paragraph a node translation references.
   */
  protected function getRow(NodeInterface $node, string $langcode): ParagraphInterface {
    $row = $node->getTranslation($langcode)->get('field_paragraph')->entity;
    $this->assertInstanceOf(ParagraphInterface::class, $row);
    return $row;
  }

  /**
   * Loads a node without the static cache.
   */
  protected function reloadNode(int $nid): NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$nid]);
    $this->container->get('entity_type.manager')->getStorage('paragraph')->resetCache();
    return $storage->load($nid);
  }

  /**
   * Creates a translatable field.
   */
  protected function createField(string $entity_type_id, string $bundle, string $field_name, string $type, array $settings = []): void {
    if (!FieldStorageConfig::loadByName($entity_type_id, $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type_id,
        'type' => $type,
        'cardinality' => -1,
        'settings' => $settings,
      ])->save();
    }
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'translatable' => TRUE,
    ])->save();
  }

}
