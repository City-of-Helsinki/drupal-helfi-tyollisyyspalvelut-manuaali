<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_forms\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests service location edit forms.
 *
 * @group hel_tpm_forms
 */
class ServiceLocationEditFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'hel_tpm_forms',
    'address',
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'service_location',
      'name' => 'Service location',
    ]);

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_address',
      'entity_type' => 'node',
      'type' => 'address',
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_name' => 'field_address',
      'entity_type' => 'node',
      'bundle' => 'service_location',
      'label' => 'Test address field',
    ]);
    $field->save();

    $this->drupalCreateNode([
      'type' => 'service_location',
      'field_address' => [
        'organization' => 'Test organization',
        'address_line1' => 'Test address 1',
        'postal_code' => '00001',
        'locality' => 'Test city',
        'country_code' => 'FI',
      ],
    ]);

    $this->account = $this->drupalCreateUser([
      'access content',
      'create service_location content',
      'edit any service_location content',
    ], 'Test user');
    $this->drupalLogin($this->account);
  }

  /**
   * Tests access to title field.
   */
  public function testTitleFieldAccess() {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([1]);

    $this->drupalGet('node/1/edit');
    $this->assertCount(0, $this->cssSelect('#edit-title-0-value'), 'Title field should be hidden.');
  }

}
