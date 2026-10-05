<?php

declare(strict_types=1);

namespace Drupal\Tests\views_exposed_embed\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\views\Entity\View;
use Drupal\views_exposed_embed\Plugin\Field\FieldType\ViewsExposedEmbedFieldItem;
use Drupal\views_exposed_embed\Plugin\Field\FieldType\ViewsExposedEmbedFieldItemList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Views exposed embed field type and item list.
 */
#[CoversClass(ViewsExposedEmbedFieldItem::class)]
#[CoversClass(ViewsExposedEmbedFieldItemList::class)]
#[Group('views_exposed_embed')]
#[RunTestsInSeparateProcesses]
class ViewsExposedEmbedFieldItemTest extends ViewsExposedEmbedKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // A view without an exposed embed display must never be offered.
    View::create([
      'id' => 'no_embed',
      'label' => 'No embed',
      'base_table' => 'entity_test',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_title' => 'Default',
          'display_plugin' => 'default',
          'position' => 0,
          'display_options' => [],
        ],
      ],
    ])->save();

    // A disabled view with an exposed embed display must not be offered.
    $disabled = View::load(static::VIEW_ID)->createDuplicate();
    $disabled->set('id', 'disabled_embed');
    $disabled->set('label', 'Disabled embed view');
    $disabled->set('status', FALSE);
    $disabled->save();
  }

  /**
   * Returns a new, unsaved field item of the given field.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return \Drupal\views_exposed_embed\Plugin\Field\FieldType\ViewsExposedEmbedFieldItem
   *   The field item.
   */
  protected function createItem(string $field_name = self::FIELD_NAME): ViewsExposedEmbedFieldItem {
    $entity = EntityTest::create(['type' => 'host']);
    return $entity->get($field_name)->appendItem();
  }

  /**
   * Tests the field type definition and default settings.
   */
  public function testFieldDefinition(): void {
    $this->assertSame(
      ['view_id' => '', 'display_id' => ''],
      array_intersect_key(ViewsExposedEmbedFieldItem::defaultFieldSettings(), ['view_id' => 1, 'display_id' => 1])
    );

    $entity = $this->createHost(['type' => ['foo']]);
    $this->assertInstanceOf(ViewsExposedEmbedFieldItemList::class, $entity->get(static::FIELD_NAME));
    $this->assertInstanceOf(ViewsExposedEmbedFieldItem::class, $entity->get(static::FIELD_NAME)->first());
  }

  /**
   * Tests that the stored filters survive a save and load round trip.
   */
  public function testValueRoundTrip(): void {
    $filters = ['type' => ['foo', 'bar'], 'name' => 'Foo', 'name_group' => '1'];
    $host = $this->createHost($filters);

    $loaded = EntityTest::load($host->id());
    $this->assertSame([['value' => $filters]], $loaded->get(static::FIELD_NAME)->getValue());
  }

  /**
   * Tests that stored values are not interpreted as markup when saved.
   */
  public function testMarkupIsStoredVerbatim(): void {
    $filters = ['name' => '<script>alert("xss")</script>'];
    $host = $this->createHost($filters);
    $loaded = EntityTest::load($host->id());
    $this->assertSame($filters, $loaded->get(static::FIELD_NAME)->first()->getValue()['value']);
  }

  /**
   * Tests the list of views offered to site builders.
   */
  public function testGetViewOptions(): void {
    $item = $this->createItem();

    $options = $item->getViewOptions(FALSE);
    $this->assertSame([static::VIEW_ID], array_keys($options));
    $this->assertSame('Test exposed embed', (string) $options[static::VIEW_ID]);

    // Without an 'allowed_views' setting every embeddable view is allowed.
    $this->assertSame([static::VIEW_ID], array_keys($item->getViewOptions()));
  }

  /**
   * Tests that view labels are filtered for XSS.
   */
  public function testGetViewOptionsFiltersLabel(): void {
    $view = View::load(static::VIEW_ID);
    $view->set('label', '<script>alert("xss")</script>Embed');
    $view->save();

    $label = (string) $this->createItem()->getViewOptions(FALSE)[static::VIEW_ID];
    $this->assertStringNotContainsString('<script>', $label);
    $this->assertStringContainsString('Embed', $label);
  }

  /**
   * Tests the list of displays offered for a view.
   */
  public function testGetDisplayOptions(): void {
    $item = $this->createItem();

    $options = $item->getDisplayOptions(static::VIEW_ID);
    // Only enabled exposed embed displays, sorted by title.
    $this->assertSame(['exposed_embed_1', 'exposed_embed_restricted'], array_keys($options));
    $this->assertSame('Exposed Embed', (string) $options['exposed_embed_1']);

    $this->assertSame([], $item->getDisplayOptions('no_embed'));
    $this->assertSame([], $item->getDisplayOptions('disabled_embed'));
    $this->assertSame([], $item->getDisplayOptions('does_not_exist'));
  }

  /**
   * Tests the field settings form with the saved settings.
   */
  public function testFieldSettingsFormUsesSettings(): void {
    $element = $this->createItem()->fieldSettingsForm([], new FormState());

    $this->assertSame('fieldset', $element['handler']['#type']);
    $this->assertTrue($element['handler']['#attributes']['hidden']);

    $this->assertSame('select', $element['view_id']['#type']);
    $this->assertTrue($element['view_id']['#required']);
    $this->assertSame(static::VIEW_ID, $element['view_id']['#default_value']);
    $this->assertSame([static::VIEW_ID], array_keys($element['view_id']['#options']));
    $this->assertSame(ViewsExposedEmbedFieldItem::DISPLAY_ID_WRAPPER, $element['view_id']['#ajax']['wrapper']);

    $this->assertSame('exposed_embed_1', $element['display_id']['#default_value']);
    $this->assertTrue($element['display_id']['#required']);
    $this->assertSame(['exposed_embed_1', 'exposed_embed_restricted'], array_keys($element['display_id']['#options']));
    $this->assertStringContainsString(ViewsExposedEmbedFieldItem::DISPLAY_ID_WRAPPER, $element['display_id']['#prefix']);
  }

  /**
   * Tests the field settings form with a view selected by the user.
   */
  public function testFieldSettingsFormUsesUserInput(): void {
    $form_state = new FormState();
    $form_state->setUserInput(['settings' => ['view_id' => 'no_embed']]);
    $element = $this->createItem()->fieldSettingsForm([], $form_state);
    // The displays follow the view selected in the form.
    $this->assertSame([], $element['display_id']['#options']);

    $form_state = new FormState();
    $form_state->setUserInput(['view_id' => static::VIEW_ID]);
    $element = $this->createItem()->fieldSettingsForm([], $form_state);
    $this->assertSame(['exposed_embed_1', 'exposed_embed_restricted'], array_keys($element['display_id']['#options']));
  }

  /**
   * Tests the field settings form without saved settings.
   */
  public function testFieldSettingsFormDefaultsToFirstView(): void {
    FieldConfig::create([
      'field_name' => static::FIELD_NAME,
      'entity_type' => 'entity_test',
      'bundle' => 'foo',
    ])->save();
    $item = EntityTest::create(['type' => 'foo'])->get(static::FIELD_NAME)->appendItem();

    $element = $item->fieldSettingsForm([], new FormState());
    $this->assertSame('', $element['view_id']['#default_value']);
    $this->assertSame(['exposed_embed_1', 'exposed_embed_restricted'], array_keys($element['display_id']['#options']));
  }

  /**
   * Tests the display selection AJAX callback.
   */
  public function testDisplayTypeOptionsAjax(): void {
    $form = ['settings' => ['display_id' => ['#type' => 'select', '#options' => ['a' => 'A']]]];
    $this->assertSame(
      $form['settings']['display_id'],
      $this->createItem()->getDisplayTypeOptionsAjax($form, new FormState())
    );
  }

  /**
   * Tests the item list equality check used for change detection.
   */
  public function testItemListEquals(): void {
    $list = fn(array $values) => EntityTest::create([
      'type' => 'host',
      static::FIELD_NAME => $values,
    ])->get(static::FIELD_NAME);
    // A list with a single item holding the given filters.
    $single = fn(array $filters) => $list([['value' => $filters]]);

    $filters = ['type' => ['foo', 'bar'], 'name' => 'Foo'];

    // Both empty.
    $this->assertTrue($list([])->equals($list([])));
    // Different number of items.
    $this->assertFalse($single($filters)->equals($list([])));
    $this->assertFalse($single($filters)->equals($list([
      ['value' => $filters],
      ['value' => $filters],
    ])));
    // Same values.
    $this->assertTrue($single($filters)->equals($single($filters)));
    // Different nested value.
    $this->assertFalse($single($filters)->equals($single(['type' => ['foo', 'baz'], 'name' => 'Foo'])));
    // Different nested count.
    $this->assertFalse($single($filters)->equals($single(['type' => ['foo'], 'name' => 'Foo'])));
    // Different keys.
    $this->assertFalse($single($filters)->equals($single(['type' => ['foo', 'bar'], 'label' => 'Foo'])));
    // Different scalar, compared strictly.
    $this->assertFalse($single(['name' => '1'])->equals($single(['name' => 1])));
    // Array against scalar.
    $this->assertFalse($single(['name' => ['Foo']])->equals($single(['name' => 'Foo'])));
  }

}
