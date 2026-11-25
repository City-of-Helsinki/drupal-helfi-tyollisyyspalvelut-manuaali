<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_audit_log\Kernel;

use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\hel_tpm_audit_log\AuditLog\AuditLogServiceInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that paragraph changes are included in the node update diff.
 */
#[Group('hel_tpm_audit_log')]
#[RunTestsInSeparateProcesses]
class AuditLogParagraphsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'file',
    'entity_reference_revisions',
    'paragraphs',
    'diff',
    'hel_tpm_audit_log',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('hel_tpm_audit_log', ['helfi_audit_logs']);
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['system', 'node']);

    ParagraphsType::create(['id' => 'text', 'label' => 'Text'])->save();
    $this->createField('paragraph', 'text', 'field_text', 'string', []);
    $this->createDisplay('paragraph', 'text', 'field_text', 'string');

    // A paragraph type with nested paragraphs.
    ParagraphsType::create(['id' => 'container', 'label' => 'Container'])->save();
    $this->createField('paragraph', 'container', 'field_children', 'entity_reference_revisions', [
      'target_type' => 'paragraph',
    ]);
    $this->createDisplay('paragraph', 'container', 'field_children', 'entity_reference_revisions_entity_view');

    // Like the site's service (new revision) and service_location (no new
    // revision) node types.
    foreach (['revisioned' => TRUE, 'unrevisioned' => FALSE] as $type => $newRevision) {
      NodeType::create([
        'type' => $type,
        'name' => $type,
        'new_revision' => $newRevision,
      ])->save();
      $this->createField('node', $type, 'field_paragraph', 'entity_reference_revisions', [
        'target_type' => 'paragraph',
      ]);
      $this->createDisplay('node', $type, 'field_paragraph', 'entity_reference_revisions_entity_view');
    }

    $this->setUpCurrentUser();
  }

  /**
   * Shows a field in the default view display.
   *
   * The diff module only compares fields that are shown in a view display.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $fieldName
   *   The field name.
   * @param string $formatter
   *   The formatter.
   */
  private function createDisplay(string $entityType, string $bundle, string $fieldName, string $formatter): void {
    EntityViewDisplay::create([
      'targetEntityType' => $entityType,
      'bundle' => $bundle,
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent($fieldName, ['type' => $formatter])->save();
  }

  /**
   * Data provider: node bundle and whether it creates new revisions.
   *
   * @return array<string, array{string}>
   *   The node bundles.
   */
  public static function nodeBundles(): array {
    return [
      'new revision' => ['revisioned'],
      'no new revision' => ['unrevisioned'],
    ];
  }

  /**
   * Tests that editing a paragraph through its node is in the node diff.
   */
  #[DataProvider('nodeBundles')]
  public function testEditedParagraphIsInNodeDiff(string $bundle): void {
    $node = $this->createNode($bundle, [$this->createTextParagraph('Before')]);

    $node = Node::load($node->id());
    $this->markChanged($node->get('field_paragraph')->entity->set('field_text', 'After'));
    $this->saveLikeNodeForm($node);

    $diff = $this->getNodeDiff();
    $this->assertCount(1, $diff);
    $this->assertStringContainsString('-Before', $diff[0]);
    $this->assertStringContainsString('+After', $diff[0]);
  }

  /**
   * Tests a paragraph saved on its own before the node, like IEF does.
   *
   * The service form edits field_municipality_guidance paragraphs with an
   * inline entity form widget, which saves the paragraph in place on form
   * submit. Saving the node then saves the paragraph again.
   */
  #[DataProvider('nodeBundles')]
  public function testParagraphSavedBeforeNodeIsInNodeDiff(string $bundle): void {
    $node = $this->createNode($bundle, [$this->createTextParagraph('Before')]);

    $node = Node::load($node->id());
    $node->get('field_paragraph')->entity->set('field_text', 'After')->save();
    // Other entities are saved before the node's audit hook runs, e.g. the
    // content moderation state in content_moderation's hook_entity_update.
    $this->createUser()->save();
    $this->saveLikeNodeForm($node);

    $diff = $this->getNodeDiff();
    $this->assertCount(1, $diff);
    $this->assertStringContainsString('-Before', $diff[0]);
    $this->assertStringContainsString('+After', $diff[0]);
  }

  /**
   * Tests that added and removed paragraphs are in the node diff.
   */
  #[DataProvider('nodeBundles')]
  public function testAddedAndRemovedParagraphsAreInNodeDiff(string $bundle): void {
    $node = $this->createNode($bundle, [$this->createTextParagraph('Removed')]);

    $node = Node::load($node->id());
    $node->set('field_paragraph', [$this->createTextParagraph('Added')]);
    $this->saveLikeNodeForm($node);

    $diff = implode("\n", $this->getNodeDiff());
    $this->assertStringContainsString('[- Removed]', $diff);
    $this->assertStringContainsString('[+ Added]', $diff);
  }

  /**
   * Tests that editing a nested paragraph is in the node diff.
   */
  #[DataProvider('nodeBundles')]
  public function testEditedNestedParagraphIsInNodeDiff(string $bundle): void {
    $container = Paragraph::create([
      'type' => 'container',
      'field_children' => [$this->createTextParagraph('Before')],
    ]);
    $node = $this->createNode($bundle, [$container]);

    $node = Node::load($node->id());
    $container = $node->get('field_paragraph')->entity;
    $this->markChanged($container);
    $this->markChanged($container->get('field_children')->entity->set('field_text', 'After'));
    $this->saveLikeNodeForm($node);

    $diff = $this->getNodeDiff();
    $this->assertCount(1, $diff);
    $this->assertStringContainsString('-Before', $diff[0]);
    $this->assertStringContainsString('+After', $diff[0]);
  }

  /**
   * Creates an unsaved text paragraph.
   */
  private function createTextParagraph(string $text): Paragraph {
    return Paragraph::create(['type' => 'text', 'field_text' => $text]);
  }

  /**
   * Creates a node with paragraphs and empties the audit log.
   *
   * @param string $bundle
   *   The node bundle.
   * @param array<\Drupal\paragraphs\Entity\Paragraph> $paragraphs
   *   The paragraphs.
   */
  private function createNode(string $bundle, array $paragraphs): NodeInterface {
    $node = Node::create([
      'type' => $bundle,
      'title' => 'Test node',
      'field_paragraph' => $paragraphs,
    ]);
    $node->save();
    $this->clearAuditLog();

    return $node;
  }

  /**
   * Marks a paragraph as changed, like the paragraphs widget does.
   */
  private function markChanged(Paragraph $paragraph): Paragraph {
    $paragraph->setNeedsSave(TRUE);
    return $paragraph;
  }

  /**
   * Saves a node using its bundle's new revision setting, like the node form.
   */
  private function saveLikeNodeForm(NodeInterface $node): void {
    $node->setNewRevision(NodeType::load($node->bundle())->shouldCreateNewRevision());
    $node->save();
  }

  /**
   * Gets the content diff of the single node update event.
   *
   * @return list<string>
   *   The changed field diffs.
   */
  private function getNodeDiff(): array {
    $events = $this->getNodeUpdateEvents();
    $this->assertCount(1, $events);

    return array_values($events[0]['extra']['ContentDiff']);
  }

  /**
   * Creates a field and attaches it to a bundle.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $fieldName
   *   The field name.
   * @param string $type
   *   The field type.
   * @param array<string, mixed> $settings
   *   The field storage settings.
   */
  private function createField(string $entityType, string $bundle, string $fieldName, string $type, array $settings): void {
    if (!FieldStorageConfig::loadByName($entityType, $fieldName)) {
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'entity_type' => $entityType,
        'type' => $type,
        'cardinality' => -1,
        'settings' => $settings,
      ])->save();
    }
    // Untranslatable, like node.service.field_municipality_guidance. This
    // makes entity_reference_revisions save the paragraphs with the host.
    FieldConfig::create([
      'field_name' => $fieldName,
      'entity_type' => $entityType,
      'bundle' => $bundle,
      'translatable' => FALSE,
    ])->save();
  }

  /**
   * Gets the node ENTITY_UPDATE events.
   *
   * @return list<array<string, mixed>>
   *   The decoded audit events.
   */
  private function getNodeUpdateEvents(): array {
    $this->flushAuditLog();

    $messages = $this->container->get('database')
      ->select('helfi_audit_logs', 'al')
      ->fields('al', ['message'])
      ->orderBy('id')
      ->execute()
      ->fetchCol();

    $events = array_map(
      static fn (string $message) => json_decode($message, TRUE)['audit_event'],
      $messages,
    );
    return array_values(array_filter(
      $events,
      static fn (array $event) => $event['operation'] === 'ENTITY_UPDATE' && str_contains($event['message'], 'node entity'),
    ));
  }

  /**
   * Flushes queued events and empties the audit log table.
   */
  private function clearAuditLog(): void {
    $this->flushAuditLog();
    $this->container->get('database')->truncate('helfi_audit_logs')->execute();
  }

  /**
   * Writes the queued audit log events to the database.
   */
  private function flushAuditLog(): void {
    $service = $this->container->get(AuditLogServiceInterface::class);
    $this->assertInstanceOf(DestructableInterface::class, $service);
    $service->destruct();
  }

}
