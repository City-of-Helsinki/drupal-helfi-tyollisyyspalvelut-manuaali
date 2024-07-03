<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_forms\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\group\Traits\NodeTypeCreationTrait;

/**
 * Tests generating service location title.
 *
 * @group hel_tpm_forms
 */
final class ServiceLocationTitleTest extends EntityKernelTestBase {

  use NodeTypeCreationTrait;

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
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->installConfig(['address']);

    $this->createNodeType(['type' => 'service_location', 'Service location']);

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
  }

  /**
   * Tests with organization information.
   */
  public function testWithOrganization() {
    $node = Node::create([
      'title' => t('Old title'),
      'type' => 'service_location',
    ]);
    $node->set('field_address', [
      'organization' => 'Test organization',
      'address_line1' => 'Test address 1',
      'postal_code' => '00001',
      'locality' => 'Test city',
      'country_code' => 'FI',
    ]);
    $node->save();

    $node = $this->reloadEntity($node);
    $this->assertEquals('Test address 1, 00001 Test city (Test organization)', $node->get('title')->value);
  }

  /**
   * Tests without organization information.
   */
  public function testWithoutOrganization() {
    $node = Node::create([
      'title' => t('Old title'),
      'type' => 'service_location',
    ]);
    $node->set('field_address', [
      'organization' => '',
      'address_line1' => 'Test address 1',
      'postal_code' => '00001',
      'locality' => 'Test city',
      'country_code' => 'FI',
    ]);
    $node->save();

    $node = $this->reloadEntity($node);
    $this->assertEquals('Test address 1, 00001 Test city', $node->get('title')->value);
  }

}
