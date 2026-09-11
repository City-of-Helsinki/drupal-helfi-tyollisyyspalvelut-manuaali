<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_general\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\inline_entity_form\FunctionalJavascript\InlineEntityFormTestBase;

/**
 * Tests the municipality specific inline entity form complex widget.
 *
 * @group inline_entity_form
 * @group hel_tpm_general
 */
final class InlineEntityFormComplexMunicipalitySpecificWidgetTest extends InlineEntityFormTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hel_tpm_general',
    'inline_entity_form_test',
    'field',
    'field_ui',
    'purge',
  ];

  /**
   * URL to add new content.
   *
   * @var string
   */
  protected string $formContentAddUrl;

  /**
   * Entity form display storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $entityFormDisplayStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createMunicipalitySpecificTestBundle();

    $this->user = $this->createUser([
      'bypass node access',
      'create ief_reference_type content',
      'edit any ief_reference_type content',
      'delete any ief_reference_type content',
      'create ief_test_complex content',
      'edit any ief_test_complex content',
      'delete any ief_test_complex content',
      'create ief_test_nested1 content',
      'edit any ief_test_nested1 content',
      'create ief_test_nested2 content',
      'edit any ief_test_nested2 content',
      'create ief_test_nested3 content',
      'edit any ief_test_nested3 content',
      'view own unpublished content',
      'administer content types',
    ]);
    $this->drupalLogin($this->user);

    $this->formContentAddUrl = 'node/add/ief_test_cmplx_munici_spec';
    $this->entityFormDisplayStorage = $this->container
      ->get('entity_type.manager')
      ->getStorage('entity_form_display');
  }

  /**
   * Creates the content type, field, and form display used by this test.
   */
  protected function createMunicipalitySpecificTestBundle(): void {
    if (!NodeType::load('ief_test_cmplx_munici_spec')) {
      NodeType::create([
        'type' => 'ief_test_cmplx_munici_spec',
        'name' => 'IEF test complex municipality specific',
        'description' => 'Content type for IEF complex municipality specific widget testing.',
      ])->save();
    }

    if (!FieldStorageConfig::load('node.multi')) {
      FieldStorageConfig::create([
        'field_name' => 'multi',
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
        'settings' => [
          'target_type' => 'node',
        ],
      ])->save();
    }

    if (!FieldConfig::load('node.ief_test_cmplx_munici_spec.multi')) {
      FieldConfig::create([
        'field_name' => 'multi',
        'entity_type' => 'node',
        'bundle' => 'ief_test_cmplx_munici_spec',
        'label' => 'Multiple nodes',
        'description' => 'Reference multiple nodes.',
        'required' => TRUE,
        'settings' => [
          'handler' => 'default:node',
          'handler_settings' => [
            'target_bundles' => [
              'ief_reference_type' => 'ief_reference_type',
            ],
            'sort' => [
              'field' => '_none',
            ],
          ],
        ],
      ])->save();
    }

    $form_display = EntityFormDisplay::load('node.ief_test_cmplx_munici_spec.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => 'ief_test_cmplx_munici_spec',
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }

    $form_display
      ->setComponent('title', [
        'type' => 'string_textfield',
        'weight' => 0,
        'settings' => [
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setComponent('multi', [
        'type' => 'municipality_specific_ief_widget',
        'weight' => 30,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'allow_existing' => FALSE,
          'removed_reference' => 'delete',
          'override_labels' => FALSE,
          'label_singular' => '',
          'label_plural' => '',
        ],
      ])
      ->save();
  }

  /**
   * Tests that referenced entities can be removed without confirmation.
   */
  public function testEntityRemoving(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $field_config = FieldConfig::load('node.ief_test_cmplx_munici_spec.multi');
    $this->assertNotEmpty($field_config, 'The multi field is installed on ief_test_cmplx_munici_spec.');

    $reference_nodes = $this->createReferenceContent();
    $parent_node = $this->drupalCreateNode([
      'type' => 'ief_test_cmplx_munici_spec',
      'title' => 'Some title',
      'multi' => array_values($reference_nodes),
    ]);

    $this->assertInstanceOf(NodeInterface::class, $parent_node);
    $this->assertSame('Some title', $parent_node->label());

    $this->drupalGet('node/' . $parent_node->id() . '/edit');
    $assert_session->elementsCount('css', 'tr.ief-row-entity', 3);
    $this->assertRowByTitle('Some reference 1');
    $this->assertRowByTitle('Some reference 2');
    $this->assertRowByTitle('Some reference 3');

    // Remove the second inline entity. The custom widget should remove it
    // immediately without showing the default confirmation form.
    $assert_session->elementExists('xpath', '(//input[@value="Remove"])[2]')
      ->press();

    $this->waitForRowRemovedByTitle('Some reference 2');
    $assert_session->pageTextNotContains('Are you sure you want to remove Some reference 2?');
    $assert_session->pageTextNotContains('Delete this node from the system.');
    $assert_session->elementsCount('css', 'tr.ief-row-entity', 2);
    $this->assertRowByTitle('Some reference 1');
    $this->assertNoRowByTitle('Some reference 2');
    $this->assertRowByTitle('Some reference 3');

    $page->pressButton('Save');
    $assert_session->pageTextContains('Some title has been updated.');

    $deleted_node = $this->drupalGetNodeByTitle('Some reference 2');
    $this->assertEmpty($deleted_node, 'The removed inline entity was deleted from the site.');

    $existing_node = $this->drupalGetNodeByTitle('Some reference 3');
    $this->assertNotEmpty($existing_node, 'The still referenced inline entity was not deleted.');

    $this->drupalGet('node/' . $parent_node->id() . '/edit');
    $assert_session->elementsCount('css', 'tr.ief-row-entity', 2);
    $this->assertRowByTitle('Some reference 1');
    $this->assertNoRowByTitle('Some reference 2');
    $this->assertRowByTitle('Some reference 3');

    // Remove the remaining second row as well.
    // This verifies that row deltas are recalculated
    // correctly after a previous removal.
    $assert_session->elementExists('xpath', '(//input[@value="Remove"])[2]')
      ->press();

    $this->waitForRowRemovedByTitle('Some reference 3');
    $assert_session->pageTextNotContains('Are you sure you want to remove Some reference 3?');
    $assert_session->pageTextNotContains('Delete this node from the system.');
    $assert_session->elementsCount('css', 'tr.ief-row-entity', 1);
    $this->assertRowByTitle('Some reference 1');
    $this->assertNoRowByTitle('Some reference 2');
    $this->assertNoRowByTitle('Some reference 3');

    $page->pressButton('Save');
    $assert_session->pageTextContains('Some title has been updated.');

    $deleted_node = $this->drupalGetNodeByTitle('Some reference 3');
    $this->assertEmpty($deleted_node, 'The second removed inline entity was deleted from the site.');

    $remaining_node = $this->drupalGetNodeByTitle('Some reference 1');
    $this->assertNotEmpty($remaining_node, 'The remaining inline entity was not deleted.');

    $this->drupalGet('node/' . $parent_node->id() . '/edit');
    $assert_session->elementsCount('css', 'tr.ief-row-entity', 1);
    $this->assertRowByTitle('Some reference 1');
    $this->assertNoRowByTitle('Some reference 2');
    $this->assertNoRowByTitle('Some reference 3');
  }

  /**
   * Creates reference nodes for the inline entity form field.
   *
   * @param int $num_nodes
   *   The number of nodes to create.
   *
   * @return array<string, int>
   *   Created node IDs keyed by node labels.
   */
  protected function createReferenceContent(int $num_nodes = 3): array {
    $reference_nodes = [];

    for ($i = 1; $i <= $num_nodes; $i++) {
      $this->drupalCreateNode([
        'type' => 'ief_reference_type',
        'title' => 'Some reference ' . $i,
        'first_name' => 'First Name ' . $i,
        'last_name' => 'Last Name ' . $i,
      ]);

      $node = $this->drupalGetNodeByTitle('Some reference ' . $i);
      $this->assertInstanceOf(NodeInterface::class, $node);
      $this->assertSame('ief_reference_type', $node->bundle());
      $this->assertSame('Some reference ' . $i, $node->label());

      $reference_nodes[$node->label()] = (int) $node->id();
    }

    return $reference_nodes;
  }

}
