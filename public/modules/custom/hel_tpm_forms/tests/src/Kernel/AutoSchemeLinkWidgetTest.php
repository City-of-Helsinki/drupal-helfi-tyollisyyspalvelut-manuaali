<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_forms\Kernel;

use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\link\LinkItemInterface;
use Drupal\Tests\group\Traits\NodeTypeCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the auto scheme link widget.
 *
 * @group hel_tpm_forms
 */
final class AutoSchemeLinkWidgetTest extends EntityKernelTestBase {

  use NodeTypeCreationTrait;

  private const FIELD_NAME = 'field_auto_scheme_link';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'hel_tpm_forms',
    'link',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installConfig(['filter', 'node', 'system', 'user']);

    $this->createNodeType([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $this->createLinkField();
  }

  /**
   * Tests user-entered link values are converted to URIs.
   */
  #[DataProvider('userEnteredUriProvider')]
  public function testMassageFormValues(string $input, string $expected): void {
    $values = $this->createWidget()->massageFormValues([['uri' => $input]], [], new FormState());

    $this->assertSame($expected, $values[0]['uri']);
  }

  /**
   * Data provider for testMassageFormValues().
   */
  public static function userEnteredUriProvider(): array {
    return [
      'domain without scheme' => [
        'example.org',
        'https://example.org',
      ],
      'subdomain with path' => [
        'www.example.org/page1/page2?param=1#id',
        'https://www.example.org/page1/page2?param=1#id',
      ],
      'domain with port' => [
        'example.com:8080/page',
        'https://example.com:8080/page',
      ],
      'surrounding whitespace' => [
        '  example.com ',
        'https://example.com',
      ],
      'https link' => [
        'https://example.com',
        'https://example.com',
      ],
      'http link' => [
        'http://example.com',
        'http://example.com',
      ],
      'mailto link' => [
        'mailto:info@example.net',
        'mailto:info@example.net',
      ],
      'internal path' => [
        '/node/1',
        'internal:/node/1',
      ],
      'front page' => [
        '<front>',
        'internal:/',
      ],
      'fragment' => [
        '#id',
        'internal:#id',
      ],
      'entity autocomplete' => [
        'Example page (1)',
        'entity:node/1',
      ],
      'title without selection' => [
        'ExamplePage',
        'internal:ExamplePage',
      ],
      'title with spaces without selection' => [
        'Another example page',
        'internal:Another example page',
      ],
    ];
  }

  /**
   * Creates the link field.
   */
  private function createLinkField(): void {
    FieldStorageConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => 'node',
      'type' => 'link',
      'settings' => [
        'link_type' => LinkItemInterface::LINK_GENERIC,
        'title' => DRUPAL_OPTIONAL,
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Link',
    ])->save();
  }

  /**
   * Creates the auto scheme link widget plugin.
   */
  private function createWidget(): WidgetInterface {
    $field_definition = FieldConfig::loadByName('node', 'page', self::FIELD_NAME);
    $widget_manager = $this->container->get('plugin.manager.field.widget');

    return $widget_manager->createInstance('auto_scheme_link_widget', [
      'field_definition' => $field_definition,
      'form_mode' => 'default',
      'prepare' => TRUE,
      'settings' => [],
      'third_party_settings' => [],
    ]);
  }

}
