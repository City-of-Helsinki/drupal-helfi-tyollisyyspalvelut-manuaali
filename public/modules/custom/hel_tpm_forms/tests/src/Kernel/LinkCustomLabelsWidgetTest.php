<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_forms\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\link\LinkItemInterface;
use Drupal\link\LinkTitleVisibility;

/**
 * Tests the link widget with custom labels and descriptions.
 *
 * @group hel_tpm_forms
 * @coversDefaultClass \Drupal\hel_tpm_forms\Plugin\Field\FieldWidget\LinkCustomLabelsWidget
 */
class LinkCustomLabelsWidgetTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'entity_test',
    'field',
    'hel_tpm_forms',
    'link',
    // hel_tpm_forms_entity_type_alter() requires the node entity type.
    'node',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');

    FieldStorageConfig::create([
      'field_name' => 'field_link',
      'entity_type' => 'entity_test',
      'type' => 'link',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_link',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' => [
        'title' => LinkTitleVisibility::Optional->value,
        'link_type' => LinkItemInterface::LINK_EXTERNAL,
      ],
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_links',
      'entity_type' => 'entity_test',
      'type' => 'link',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_links',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Builds the entity form with the given widget settings for a field.
   *
   * @param string $field_name
   *   The field name.
   * @param array $settings
   *   Widget settings.
   *
   * @return array
   *   The field's widget element.
   */
  protected function buildFieldWidget(string $field_name, array $settings): array {
    $this->container->get('entity_display.repository')
      ->getFormDisplay('entity_test', 'entity_test')
      ->setComponent($field_name, [
        'type' => 'hel_tpm_forms_link_custom_labels',
        'settings' => $settings,
      ])
      ->save();

    $form = $this->container->get('entity.form_builder')
      ->getForm(EntityTest::create(), 'default');
    return $form[$field_name]['widget'];
  }

  /**
   * Tests the custom add more button text.
   *
   * @covers ::formMultipleElements
   */
  public function testAddMoreLabel(): void {
    $widget = $this->buildFieldWidget('field_links', ['add_more_label' => 'Add another online service']);
    $this->assertSame('Add another online service', $widget['add_more']['#value']);

    $widget = $this->buildFieldWidget('field_links', []);
    $this->assertSame('Add another item', (string) $widget['add_more']['#value']);
  }

  /**
   * Builds the link field widget element with the given widget settings.
   *
   * @param array $settings
   *   Widget settings.
   *
   * @return array
   *   The first delta of the field widget.
   */
  protected function buildWidget(array $settings): array {
    return $this->buildFieldWidget('field_link', $settings)[0];
  }

  /**
   * Tests that configured labels and descriptions override the defaults.
   *
   * @covers ::formElement
   */
  public function testCustomLabels(): void {
    $element = $this->buildWidget([
      'uri_label' => 'Online service address',
      'uri_description' => 'Enter the address of the online service.',
      'title_label' => 'Online service name',
      'title_description' => 'Shown as the link text.',
    ]);

    $this->assertSame('Online service address', $element['uri']['#title']);
    $this->assertSame('Enter the address of the online service.', $element['uri']['#description']);
    $this->assertSame('Online service name', $element['title']['#title']);
    $this->assertSame('Shown as the link text.', $element['title']['#description']);

    // Descriptions are flagged to render below the inputs.
    foreach (['uri', 'title'] as $key) {
      $this->assertSame('after', $element[$key]['#description_display']);
      $this->assertSame(['form_element__link_custom_labels'], $element[$key]['#theme_wrappers']);
    }
  }

  /**
   * Tests that the https scheme is added like in AutoSchemeLinkWidget.
   *
   * @covers ::massageFormValues
   */
  public function testAutoScheme(): void {
    $form_display = EntityFormDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $form_display->setComponent('field_link', [
      'type' => 'hel_tpm_forms_link_custom_labels',
    ]);
    $widget = $form_display->getRenderer('field_link');

    $values = $widget->massageFormValues([
      ['uri' => 'example.org/path', 'title' => ''],
      ['uri' => 'https://example.com', 'title' => ''],
    ], [], new FormState());

    $this->assertSame('https://example.org/path', $values[0]['uri']);
    $this->assertSame('https://example.com', $values[1]['uri']);
  }

  /**
   * Tests that empty settings keep the core link widget defaults.
   *
   * @covers ::formElement
   */
  public function testDefaultLabels(): void {
    $element = $this->buildWidget([]);

    $this->assertSame('URL', (string) $element['uri']['#title']);
    $this->assertNotEmpty($element['uri']['#description']);
    $this->assertSame('Link text', (string) $element['title']['#title']);
    $this->assertArrayNotHasKey('#description', $element['title']);
  }

}
