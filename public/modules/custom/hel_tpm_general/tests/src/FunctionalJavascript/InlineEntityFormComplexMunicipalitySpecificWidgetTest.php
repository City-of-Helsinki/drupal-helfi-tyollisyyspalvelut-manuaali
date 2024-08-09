<?php

declare(strict_types = 1);

namespace Drupal\Tests\hel_tpm_general\FunctionalJavascript;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormBase;
use Drupal\Tests\inline_entity_form\FunctionalJavascript\InlineEntityFormTestBase;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Test Inline entity form complex municipality specific widget functionality.
 *
 * @group inline_entity_form
 */
class InlineEntityFormComplexMunicipalitySpecificWidgetTest extends InlineEntityFormTestBase {
  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
  }
  protected static $modules = [
    'hel_tpm_general_inline_entity_form_complex_test',
    'field',
    'field_ui'
  ];

  /**
   * URL to add new content.
   *
   * @var string
   */
  protected $formContentAddUrl;

  /**
   * Entity form display storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $entityFormDisplayStorage;

  protected function setUp(): void {
    parent::setUp();

    $this->user = $this->createUser([
      'create ief_reference_type content',
      'create ief_test_nested1 content',
      'create ief_test_nested2 content',
      'create ief_test_nested3 content',
      'edit any ief_reference_type content',
      'delete any ief_reference_type content',
      'create ief_test_complex content',
      'edit any ief_test_complex content',
      'delete any ief_test_complex content',
      'edit any ief_test_nested1 content',
      'edit any ief_test_nested2 content',
      'edit any ief_test_nested3 content',
      'view own unpublished content',
      'administer content types',
    ]);
    $this->drupalLogin($this->user);

    $this->formContentAddUrl = 'node/add/ief_test_complex_municipality';
    $this->entityFormDisplayStorage = $this->container->get('entity_type.manager')->getStorage('entity_form_display');
  }

  /**
   * Tests if editing and removing entities work.
   */
  public function testEntityRemoving() {
    // Get the xpath selectors for the fields in this test.
    $inner_title_field_xpath = $this->getXpathForNthInputByLabelText('Title', 2);
    $first_name_field_xpath = $this->getXpathForNthInputByLabelText('First name', 1);
    $last_name_field_xpath = $this->getXpathForNthInputByLabelText('Last name', 1);
    $first_delete_checkbox_xpath = $this->getXpathForNthInputByLabelText('Delete this node from the system.', 1);

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Create three ief_reference_type entities.
    $referenceNodes = $this->createReferenceContent();
    $this->drupalCreateNode([
      'type' => 'ief_test_complex_municipality_specific',
      'title' => 'Some title',
      'multi' => array_values($referenceNodes),
    ]);

    $parent_node = $this->drupalGetNodeByTitle('Some title');

    // Delete the second entity.
    $this->drupalGet('node/' . $parent_node->id() . '/edit');
    $assert_session->elementsCount('css', 'tr.ief-row-entity', 3);
    $assert_session->elementExists('xpath', '(//input[@value="Remove"])[2]')
      ->press();
    $this->waitForRowRemovedByTitle('Some reference 2');
    // Assert two rows show, instead of 3.
    $assert_session->elementsCount('css', 'tr.ief-row-entity', 2);

    // Save the ief_test_complex node.
    $page->pressButton('Save');
    $assert_session->pageTextContains('IEF test complex Some title has been updated.');

    $deleted_node = $this->drupalGetNodeByTitle('Some reference 2');
    $this->assertEmpty($deleted_node, 'The inline entity was deleted from the site.');

    // Checks that entity does nor appear in IEF.
    $this->drupalGet('node/' . $parent_node->id() . '/edit');
    // Assert 2 rows show, instead of 3.
    $assert_session->elementsCount('css', 'tr.ief-row-entity', 2);
    $this->assertRowByTitle('Some reference 1');
    $this->assertNoRowByTitle('Some reference 2');
    $this->assertRowByTitle('Some reference 3');

    // Delete the third entity reference only, don't delete the node. The third
    // entity now is second referenced entity because the second one was deleted
    // in previous step.
    $this->drupalGet('node/' . $parent_node->id() . '/edit');
    $assert_session->elementsCount('css', 'tr.ief-row-entity', 2);
    $assert_session->elementExists('xpath', '(//input[@value="Remove"])[2]')
      ->press();
    $this->assertNotEmpty($assert_session->waitForElement('xpath', $first_delete_checkbox_xpath));
    $assert_session->pageTextContains('Are you sure you want to remove Some reference 3?');
    $assert_session->elementExists('xpath', '(//input[@value="Remove"])[2]')
      ->press();
    $this->waitForRowRemovedByTitle('Some reference 3');
    // Assert only one row displays.
    $assert_session->elementsCount('css', 'tr.ief-row-entity', 1);
    $this->assertRowByTitle('Some reference 1');
    $this->assertNoRowByTitle('Some reference 2');
    $this->assertNoRowByTitle('Some reference 3');

    // Save the ief_test_complex node.
    $page->pressButton('Save');
    $assert_session->pageTextContains('IEF test complex Some title has been updated.');

    // Checks that entity is not deleted.
    $node = $this->drupalGetNodeByTitle('Some reference 3');
    $this->assertNotEmpty($node, 'Reference node not deleted');
  }
}
