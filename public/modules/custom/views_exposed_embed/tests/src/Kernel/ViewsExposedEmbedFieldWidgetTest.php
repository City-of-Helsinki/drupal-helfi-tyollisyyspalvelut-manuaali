<?php

declare(strict_types=1);

namespace Drupal\Tests\views_exposed_embed\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\views_exposed_embed\Plugin\Field\FieldWidget\ViewsExposedEmbedFieldWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Tests the Views exposed embed field widget.
 */
#[CoversClass(ViewsExposedEmbedFieldWidget::class)]
#[Group('views_exposed_embed')]
#[RunTestsInSeparateProcesses]
class ViewsExposedEmbedFieldWidgetTest extends ViewsExposedEmbedKernelTestBase {

  /**
   * Creates a widget instance for the test field.
   *
   * @return \Drupal\views_exposed_embed\Plugin\Field\FieldWidget\ViewsExposedEmbedFieldWidget
   *   The widget.
   */
  protected function createWidget(): ViewsExposedEmbedFieldWidget {
    return $this->container->get('plugin.manager.field.widget')->getInstance([
      'field_definition' => $this->field,
      'form_mode' => 'default',
      'configuration' => [
        'type' => 'views_exposed_embed_field_widget',
        'settings' => [],
        'third_party_settings' => [],
      ],
    ]);
  }

  /**
   * Builds the widget form element for a host with the given presets.
   *
   * @param array|null $filters
   *   The stored preset filters.
   * @param array $parents
   *   The field parents.
   *
   * @return array
   *   The form element.
   */
  protected function buildElement(?array $filters = [], array $parents = []): array {
    $host = EntityTest::create(['type' => 'host', static::FIELD_NAME => ['value' => $filters]]);
    $form = [];
    return $this->createWidget()->formElement(
      $host->get(static::FIELD_NAME),
      0,
      ['#field_parents' => $parents],
      $form,
      new FormState()
    );
  }

  /**
   * Tests that the widget is the default widget of the field type.
   */
  public function testDefaultWidget(): void {
    $this->assertInstanceOf(ViewsExposedEmbedFieldWidget::class, $this->createWidget());
  }

  /**
   * Tests the form element built from the exposed filters of the view.
   */
  public function testFormElement(): void {
    $element = $this->buildElement();

    $this->assertSame(static::FIELD_NAME, $element['#name']);
    $this->assertSame(['container', 'form_element'], $element['#theme_wrappers']);
    $this->assertContains('container-inline', $element['#attributes']['class']);
    $this->assertContains('hel-tpm-general-configurable-search-result-elements', $element['#attributes']['class']);
    $this->assertContains('hel_tpm_general/hel_tpm_general_configurable_search_result', $element['#attached']['library']);

    // Only the exposed filters are offered, fixed filters are not.
    $this->assertSame(['type', 'name', 'name_group'], array_keys($element['value']));
    $this->assertArrayNotHasKey('name_hidden', $element['value']);

    $type = $element['value']['type'];
    $this->assertSame('select', $type['#type']);
    $this->assertTrue($type['#multiple']);
    $this->assertArrayHasKey('foo', $type['#options']);
    $this->assertArrayHasKey('bar', $type['#options']);
    $this->assertSame([], $type['#default_value']);

    // A textfield following a select must not get an array default value.
    $this->assertSame('textfield', $element['value']['name']['#type']);
    $this->assertNull($element['value']['name']['#default_value']);

    $this->assertSame('select', $element['value']['name_group']['#type']);
    $this->assertSame([], $element['value']['name_group']['#default_value']);

    // Only whitelisted form properties are copied from the exposed form.
    foreach ($element['value'] as $filter) {
      $this->assertSame([], array_diff(array_keys($filter), [
        '#type',
        '#title',
        '#description',
        '#default_value',
        '#options',
        '#require',
        '#multiple',
        '#value',
      ]));
    }
  }

  /**
   * Tests that stored presets are used as default values.
   */
  public function testFormElementDefaultValues(): void {
    $element = $this->buildElement(['type' => ['foo'], 'name' => 'Foo', 'name_group' => '1']);
    $this->assertSame(['foo'], $element['value']['type']['#default_value']);
    $this->assertSame('Foo', $element['value']['name']['#default_value']);
    $this->assertSame('1', $element['value']['name_group']['#default_value']);

    $element = $this->buildElement(NULL);
    $this->assertSame([], $element['value']['type']['#default_value']);
    $this->assertNull($element['value']['name']['#default_value']);
  }

  /**
   * Tests the element name inside a nested form such as a paragraph.
   */
  public function testFormElementNestedName(): void {
    $element = $this->buildElement([], ['field_paragraphs', '0', 'subform']);
    $this->assertStringStartsWith('field_paragraphs[0][subform][' . static::FIELD_NAME, $element['#name']);
  }

  /**
   * Tests that the widget is empty without a valid view.
   */
  public function testFormElementWithoutView(): void {
    $this->field->setSetting('view_id', '');
    $this->assertSame([], $this->buildElement()['value']);

    $this->field->setSetting('view_id', 'does_not_exist');
    $this->assertSame([], $this->buildElement()['value']);
  }

  /**
   * Tests the massaging of the submitted values.
   */
  public function testMassageFormValues(): void {
    $values = [
      [
        'value' => [
          // Unchecked options are submitted as 0.
          'type' => ['foo' => 'foo', 'bar' => 0, 'host' => '0', 'entity_test' => ''],
          'name' => 'Foo',
          'name_group' => [],
        ],
      ],
      ['value' => []],
      ['value' => NULL],
      ['value' => 'not an array'],
    ];

    $form = [];
    $massaged = $this->createWidget()->massageFormValues($values, $form, new FormState());

    $this->assertSame([
      [
        'value' => [
          'type' => ['foo' => 'foo'],
          'name' => 'Foo',
          'name_group' => [],
        ],
      ],
      ['value' => NULL],
      ['value' => NULL],
      ['value' => NULL],
    ], $massaged);
  }

  /**
   * Tests that violations are shown on the offending filter element.
   */
  public function testErrorElement(): void {
    $violation = $this->createMock(ConstraintViolationInterface::class);
    $violation->method('getPropertyPath')->willReturn('0.value');

    $element = [
      'value' => ['type' => ['#type' => 'select']],
      'other' => ['#type' => 'textfield'],
    ];
    $form = [];
    $result = $this->createWidget()->errorElement($element, $violation, $form, new FormState());
    $this->assertSame($element['value'], $result);
  }

}
