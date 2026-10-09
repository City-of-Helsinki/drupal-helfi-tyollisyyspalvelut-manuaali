<?php

declare(strict_types=1);

namespace Drupal\Tests\views_exposed_embed\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Drupal\views_exposed_embed\Plugin\views\display\ExposedEmbed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Exposed Embed views display plugin.
 */
#[CoversClass(ExposedEmbed::class)]
#[Group('views_exposed_embed')]
#[RunTestsInSeparateProcesses]
class ExposedEmbedDisplayTest extends ViewsExposedEmbedKernelTestBase {

  /**
   * Loads the test view on the given display.
   *
   * @param string $display_id
   *   The display ID.
   *
   * @return \Drupal\views\ViewExecutable
   *   The view.
   */
  protected function getView(string $display_id = 'exposed_embed_1'): ViewExecutable {
    $view = Views::getView(static::VIEW_ID);
    $view->setDisplay($display_id);
    return $view;
  }

  /**
   * Builds the preset argument the field formatter passes to the view.
   *
   * @param array $filters
   *   The preset filter values.
   *
   * @return string
   *   The JSON encoded argument.
   */
  protected function presetArgument(array $filters): string {
    return Json::encode(['exposed_embed' => $filters]);
  }

  /**
   * Previews the view and returns the names of the result entities.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param array $args
   *   The view arguments.
   *
   * @return string[]
   *   The entity names in result order.
   */
  protected function resultNames(ViewExecutable $view, array $args = []): array {
    $view->preview(NULL, $args);
    return array_map(static fn($row) => $row->_entity->label(), $view->result);
  }

  /**
   * Tests the plugin definition level behaviour.
   */
  public function testDisplayPlugin(): void {
    $view = $this->getView();
    $this->assertInstanceOf(ExposedEmbed::class, $view->display_handler);
    $this->assertTrue($view->display_handler->displaysExposed());
    $this->assertTrue($view->display_handler->usesExposedFormInBlock());
  }

  /**
   * Tests that a view without input or presets is not filtered.
   */
  public function testNoInput(): void {
    $this->setRequestQuery([]);
    $view = $this->getView();
    $view->preExecute();
    $this->assertSame([], $view->getExposedInput());
    $this->assertSame(['Foo item', 'Bar item'], $this->resultNames($this->getView()));
  }

  /**
   * Tests that query parameters the view does not expose are dropped.
   */
  public function testUnknownQueryParametersAreDropped(): void {
    $this->setRequestQuery([
      'service_set' => ['191', '191', '191'],
      'name' => 'Foo',
      'name_op' => 'contains',
      'name_group' => '1',
      'sort_by' => 'id',
      'sort_order' => 'ASC',
      'items_per_page' => '5',
      'type_op' => 'not in',
      'name_hidden' => 'zzz',
      'evil' => '<script>alert(1)</script>',
    ]);
    $view = $this->getView();
    $view->preExecute();

    $this->assertSame([
      'name' => 'Foo',
      'name_op' => 'contains',
      'name_group' => '1',
      'sort_by' => 'id',
      'sort_order' => 'ASC',
      'items_per_page' => '5',
    ], $view->getExposedInput());
  }

  /**
   * Tests that repeated values of a multi-value filter are collapsed.
   */
  public function testDuplicateValuesAreRemoved(): void {
    $this->setRequestQuery(['type' => ['foo', 'foo', 'bar', 'foo', 'bar']]);
    $view = $this->getView();
    $view->preExecute();
    $this->assertSame(['type' => ['foo', 'bar']], $view->getExposedInput());
  }

  /**
   * Tests that keyed multi-value input (e.g. checkboxes) is left intact.
   */
  public function testKeyedValuesAreKept(): void {
    $this->setRequestQuery(['type' => ['foo' => 'foo', 'bar' => 'bar']]);
    $view = $this->getView();
    $view->preExecute();
    $this->assertSame(['type' => ['foo' => 'foo', 'bar' => 'bar']], $view->getExposedInput());
  }

  /**
   * Tests that request input filters the results.
   */
  public function testRequestInputFiltersResults(): void {
    $this->setRequestQuery(['type' => ['bar', 'bar'], 'service_set' => ['1']]);
    $this->assertSame(['Bar item'], $this->resultNames($this->getView()));
  }

  /**
   * Tests that preset values from the view arguments are applied.
   */
  public function testPresetFiltersAreApplied(): void {
    $this->setRequestQuery([]);
    $view = $this->getView();
    $view->setArguments([$this->presetArgument(['type' => ['foo', 'foo']])]);
    $view->preExecute();

    $this->assertSame(['foo'], $view->filter['type']->value);
    $this->assertSame(['type' => ['foo']], $view->getExposedInput());
    $this->assertSame(['Foo item'], $this->resultNames($this->getView(), [$this->presetArgument(['type' => ['foo']])]));
  }

  /**
   * Tests that preset values take precedence over the request.
   */
  public function testPresetOverridesRequest(): void {
    $this->setRequestQuery(['type' => ['bar'], 'name' => 'item']);
    $view = $this->getView();
    $view->setArguments([$this->presetArgument(['type' => ['foo']])]);
    $view->preExecute();

    $this->assertSame(['type' => ['foo'], 'name' => 'item'], $view->getExposedInput());
  }

  /**
   * Tests that a preset for a grouped filter reaches the exposed input only.
   */
  public function testGroupedFilterPreset(): void {
    $this->setRequestQuery([]);
    $view = $this->getView();
    $view->setArguments([$this->presetArgument(['name_group' => '1'])]);
    $view->preExecute();

    $this->assertSame(['name_group' => '1'], $view->getExposedInput());
    $this->assertSame(['Foo item'], $this->resultNames($this->getView(), [$this->presetArgument(['name_group' => '1'])]));
  }

  /**
   * Tests that arguments can not change filters that are not exposed.
   *
   * The arguments are sent back by the client on AJAX requests
   * (/views/ajax view_args), so they must never be able to alter the fixed
   * filters of the view, e.g. a published status filter.
   */
  public function testPresetCanNotOverrideNonExposedFilter(): void {
    $this->setRequestQuery([]);
    $argument = $this->presetArgument([
      'name_hidden' => 'zzz',
      'name_hidden_op' => 'contains',
      'type_op' => 'not in',
      'unknown' => 'value',
    ]);

    $view = $this->getView();
    $view->setArguments([$argument]);
    $view->preExecute();
    $this->assertSame('Secret', $view->filter['name_hidden']->value);
    $this->assertSame('not', $view->filter['name_hidden']->operator);
    $this->assertSame([], $view->getExposedInput());

    $names = $this->resultNames($this->getView(), [$argument]);
    $this->assertNotContains('Secret item', $names);
    $this->assertSame(['Foo item', 'Bar item'], $names);
  }

  /**
   * Tests that request input can not change filters that are not exposed.
   */
  public function testRequestCanNotOverrideNonExposedFilter(): void {
    $this->setRequestQuery(['name_hidden' => 'zzz', 'name' => 'Secret']);
    $this->assertSame([], $this->resultNames($this->getView()));
  }

  /**
   * Tests that malformed or foreign arguments are ignored.
   */
  #[DataProvider('providerInvalidArguments')]
  public function testInvalidArgumentsAreIgnored(mixed $argument): void {
    $this->setRequestQuery([]);
    $view = $this->getView();
    $view->setArguments([$argument]);
    $view->preExecute();

    $this->assertSame([], $view->getExposedInput());
    $this->assertSame([], $view->filter['type']->value);
  }

  /**
   * Data provider for testInvalidArgumentsAreIgnored().
   *
   * @return array
   *   Test cases.
   */
  public static function providerInvalidArguments(): array {
    return [
      'no marker' => ['{"type":["foo"]}'],
      'not a string' => [123],
      'invalid json' => ['{exposed_embed: ['],
      'json string' => ['"exposed_embed"'],
      'json list' => ['["exposed_embed"]'],
      'scalar filters' => ['{"exposed_embed":"type"}'],
      'null filters' => ['{"exposed_embed":null}'],
      'other key' => ['{"not_exposed_embed":{"type":["foo"]}}'],
    ];
  }

  /**
   * Tests that the last preset argument wins.
   */
  public function testLastPresetArgumentWins(): void {
    $this->setRequestQuery([]);
    $view = $this->getView();
    $view->setArguments([
      $this->presetArgument(['type' => ['foo']]),
      'unrelated',
      $this->presetArgument(['type' => ['bar']]),
    ]);
    $view->preExecute();
    $this->assertSame(['type' => ['bar']], $view->getExposedInput());
  }

  /**
   * Tests that the 'more' link makes the view count the total rows.
   */
  public function testMoreLinkCountsTotalRows(): void {
    $this->setRequestQuery([]);
    $view = $this->getView();
    $view->display_handler->setOption('use_more', TRUE);
    $view->display_handler->setOption('use_more_always', FALSE);
    $view->preExecute();
    $this->assertTrue($view->get_total_rows);

    $view = $this->getView();
    $view->preExecute();
    $this->assertEmpty($view->get_total_rows);
  }

  /**
   * Tests that the display access settings are respected.
   */
  public function testDisplayAccess(): void {
    $view = $this->getView('exposed_embed_restricted');
    $this->assertFalse($view->access('exposed_embed_restricted'));

    $this->setUpCurrentUser([], ['view test entity']);
    $view = $this->getView('exposed_embed_restricted');
    $this->assertTrue($view->access('exposed_embed_restricted'));
  }

}
