<?php

declare(strict_types = 1);

namespace Drupal\Tests\hel_tpm_forms\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests service edit forms.
 *
 * @group hel_tpm_forms
 */
class ServiceEditFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'field',
    'text',
    'filter',
    'options',
    'content_moderation',
    'workflows',
    'node',
    'flexible_permissions',
    'entity_reference_revisions',
    'taxonomy',
    'paragraphs',
    'range',
    'require_on_publish',
    'select2',
    'maxlength',
    'inline_entity_form',
    'hel_tpm_forms',
    'hel_tpm_forms_config_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The service under test.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->account = $this->drupalCreateUser([
      'administer nodes',
      'access content',
      'create service content',
      'edit any service content',
    ], 'Test user',
    TRUE);
    $this->drupalLogin($this->account);

    $this->service = $this->drupalCreateNode([
      'type' => 'service',
    ]);

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displayRepository */
    $displayRepository = \Drupal::service('entity_display.repository');
    $displayRepository->getFormDisplay('node', 'service')
      ->setComponent('field_field_client_consent_descr', [
        'type' => 'text_textarea',
        'settings' => [],
      ])
      ->setComponent('field_target_group', [
        'type' => 'entity_reference_paragraphs',
        'settings' => [],
      ])
      ->setComponent('field_service_provider_updatee', [
        'type' => 'options_select',
        'settings' => [],
      ])
      ->setComponent('field_service_execution', [
        'type' => 'inline_entity_form_simple',
        'settings' => [],
      ])
      ->setComponent('field_attendance', [
        'type' => 'inline_entity_form_simple',
        'settings' => [],
      ])
      ->save();
  }

  /**
   * Tests filling the age fields.
   */
  public function testAgeFields() {
    $page = $this->getSession()->getPage();
    $this->drupalGet('node/1/edit');
    $page->selectFieldOption('moderation_state[0][state]', 'published');

    // Not filling any age fields should not pass validation.
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('age group is mandatory');

    // Filling only the "age from" field should not pass validation.
    $page->fillField('edit-field-target-group-0-subform-field-age-0-from', '18');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('Both range values (FROM and TO) are required.');

    // Filling also the "age to" field should pass validation.
    $page->fillField('edit-field-target-group-0-subform-field-age-0-to', '30');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextNotContains('Both range values (FROM and TO) are required.');
    $this->assertSession()->pageTextNotContains('age group is mandatory');

    // Checking the "no age restriction" field without filling other fields
    // should pass validation.
    $this->drupalGet('node/1/edit');
    $page->selectFieldOption('moderation_state[0][state]', 'published');
    $page->fillField('edit-field-target-group-0-subform-field-age-0-from', '');
    $page->fillField('edit-field-target-group-0-subform-field-age-0-to', '');
    $page->checkField('edit-field-target-group-0-subform-field-age-groups-no-age-restriction');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextNotContains('age group is mandatory');
  }

  /**
   * Tests filling the municipality fields.
   */
  public function testMunicipalityFields() {
    $page = $this->getSession()->getPage();
    $this->drupalGet('node/1/edit');
    $page->selectFieldOption('moderation_state[0][state]', 'published');

    // Not filling any municipality fields should not pass validation.
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('municipalities is required');

    // Checking the "municipality irrelevant" field should pass validation.
    $page->checkField('edit-field-target-group-0-subform-field-municipality-irrelevant-value');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextNotContains('municipalities is required');
  }

  /**
   * Tests default value of service provider user field.
   */
  public function testServiceProviderUserDefault() {
    $fieldName = 'edit-field-service-provider-updatee';

    // Ensure the "none" option is shown and selected if the options do not
    // match the current service provider user.
    $this->drupalGet('node/1/edit');
    $this->assertTrue($this->assertSession()->optionExists($fieldName, '- Select a value -')->isSelected());

    $this->service->set('field_service_provider_updatee', $this->account);
    $this->service->save();

    // Ensure the current service provider is selected.
    $this->drupalGet('node/1/edit');
    $this->assertTrue($this->assertSession()->optionExists($fieldName, 'Test user')->isSelected());
  }

  /**
   * Tests filling the required long-text paragraph fields.
   */
  public function testRequiredParagraphTextFields() {
    $page = $this->getSession()->getPage();
    $this->drupalGet('node/1/edit');
    $page->selectFieldOption('moderation_state[0][state]', 'ready_to_publish');

    $requiredParagraphTextFields = [
      'field_service_execution' => 'Method of organizing',
      'field_attendance' => 'Participation in the service',
    ];

    // Not filling required paragraph text fields should not pass validation.
    $this->submitForm([], 'Save');
    foreach ($requiredParagraphTextFields as $fieldName => $fieldLabel) {
      $this->assertSession()->pageTextContains($fieldLabel . ': field is required');
    }

    // Filling required paragraph text fields should pass validation.
    $page->fillField('edit-field-service-execution-0-inline-entity-form-field-description-0-value', 'foo');
    $page->fillField('edit-field-attendance-0-inline-entity-form-field-description-0-value', 'bar');
    $this->submitForm([], 'Save');
    foreach ($requiredParagraphTextFields as $fieldName => $fieldLabel) {
      $this->assertSession()->pageTextNotContains($fieldLabel . ': field is required');
    }
  }



}
