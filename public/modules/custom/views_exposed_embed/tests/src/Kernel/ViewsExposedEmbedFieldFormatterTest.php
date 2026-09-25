<?php

declare(strict_types=1);

namespace Drupal\Tests\views_exposed_embed\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\views\Views;
use Drupal\views_exposed_embed\Plugin\Field\FieldFormatter\ViewsExposedEmbedFieldDefaultFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Views exposed embed default field formatter.
 */
#[CoversClass(ViewsExposedEmbedFieldDefaultFormatter::class)]
#[Group('views_exposed_embed')]
#[RunTestsInSeparateProcesses]
class ViewsExposedEmbedFieldFormatterTest extends ViewsExposedEmbedKernelTestBase {

  /**
   * The formatter plugin ID.
   */
  protected const FORMATTER = 'views_exposed_embed_field_default';

  /**
   * Creates a formatter instance for the test field.
   *
   * @param array $settings
   *   The formatter settings.
   *
   * @return \Drupal\views_exposed_embed\Plugin\Field\FieldFormatter\ViewsExposedEmbedFieldDefaultFormatter
   *   The formatter.
   */
  protected function createFormatter(array $settings = []): ViewsExposedEmbedFieldDefaultFormatter {
    return $this->container->get('plugin.manager.field.formatter')->getInstance([
      'field_definition' => $this->field,
      'view_mode' => 'default',
      'configuration' => [
        'type' => static::FORMATTER,
        'settings' => $settings,
        'label' => 'hidden',
        'third_party_settings' => [],
      ],
    ]);
  }

  /**
   * Builds the render array of the host entity's embed field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host
   *   The host entity.
   * @param array $settings
   *   The formatter settings.
   *
   * @return array
   *   The field render array.
   */
  protected function viewField(EntityInterface $host, array $settings = []): array {
    return $host->get(static::FIELD_NAME)->view([
      'type' => static::FORMATTER,
      'label' => 'hidden',
      'settings' => $settings,
    ]);
  }

  /**
   * Returns the preset filters passed to the view by the formatter.
   *
   * @param array $element
   *   The render array of a single field item.
   *
   * @return array
   *   The decoded preset filters.
   */
  protected function presetArgument(array $element): array {
    $argument = end($element['#arguments']);
    return Json::decode($argument)['exposed_embed'];
  }

  /**
   * Tests the formatter settings, summary and settings form.
   */
  public function testSettings(): void {
    $this->assertSame(['exposed_filters' => []], array_intersect_key(
      ViewsExposedEmbedFieldDefaultFormatter::defaultSettings(),
      ['exposed_filters' => 1]
    ));

    $formatter = $this->createFormatter(['exposed_filters' => ['type' => 'type', 'name' => 0, 'name_group' => '0']]);
    $summary = array_map('strval', $formatter->settingsSummary());
    $this->assertSame(['Show exposed embed filters', 'Exposed filters: type'], $summary);

    $summary = array_map('strval', $this->createFormatter()->settingsSummary());
    $this->assertSame(['Show exposed embed filters', 'Exposed filters: '], $summary);

    $form = $formatter->settingsForm([], new FormState());
    $this->assertSame('checkboxes', $form['exposed_filters']['#type']);
    $this->assertSame(['type', 'name', 'name_group'], array_keys($form['exposed_filters']['#options']));
    $this->assertArrayNotHasKey('name_hidden', $form['exposed_filters']['#options']);
    $this->assertSame(['type' => 'type', 'name' => 0, 'name_group' => '0'], $form['exposed_filters']['#default_value']);
  }

  /**
   * Tests that the settings form survives a missing view.
   */
  public function testSettingsFormWithMissingView(): void {
    $this->field->setSetting('view_id', 'does_not_exist');
    $form = $this->createFormatter()->settingsForm([], new FormState());
    $this->assertSame([], $form['exposed_filters']['#options']);
  }

  /**
   * Tests that the stored preset filters are applied to the embedded view.
   */
  public function testPresetFiltersAreApplied(): void {
    $this->setRequestQuery([]);
    $build = $this->viewField($this->createHost(['type' => ['foo']]));

    $this->assertSame(['type' => ['foo']], $this->presetArgument($build[0]));
    $this->assertArrayNotHasKey('exposed_filters', $build[0]);

    $output = (string) $this->render($build);
    $this->assertStringContainsString('Foo item', $output);
    $this->assertStringNotContainsString('Bar item', $output);
    $this->assertStringNotContainsString('Host item', $output);
    $this->assertStringNotContainsString('Secret item', $output);
  }

  /**
   * Tests that the view is not filtered without presets.
   */
  public function testWithoutPresets(): void {
    $this->setRequestQuery([]);
    $build = $this->viewField($this->createHost());

    $this->assertSame([], $this->presetArgument($build[0]));
    $output = (string) $this->render($build);
    $this->assertStringContainsString('Foo item', $output);
    $this->assertStringContainsString('Bar item', $output);
    $this->assertStringContainsString('Host item', $output);
    $this->assertStringNotContainsString('Secret item', $output);
  }

  /**
   * Tests that an empty stored value renders the unfiltered view.
   */
  public function testEmptyStoredValue(): void {
    $this->setRequestQuery([]);
    $build = $this->viewField($this->createHost(NULL));

    $this->assertSame([], $this->presetArgument($build[0]));
    $this->assertStringContainsString('Bar item', (string) $this->render($build));
  }

  /**
   * Tests that visitors can not override the presets of the editor.
   */
  public function testRequestCanNotOverridePresets(): void {
    $this->setRequestQuery([
      'type' => ['bar'],
      'name_hidden' => 'zzz',
      'service_set' => ['191', '191'],
    ]);
    $build = $this->viewField($this->createHost(['type' => ['foo']]));

    $this->assertSame(['type' => ['foo']], $this->presetArgument($build[0]));
    $output = (string) $this->render($build);
    $this->assertStringContainsString('Foo item', $output);
    $this->assertStringNotContainsString('Bar item', $output);
    $this->assertStringNotContainsString('Secret item', $output);
  }

  /**
   * Tests that filters disabled in the formatter are not read from the query.
   */
  public function testDisabledFiltersAreNotReadFromQuery(): void {
    $this->setRequestQuery(['type' => ['bar'], 'name' => 'Bar']);
    $build = $this->viewField($this->createHost(), [
      'exposed_filters' => ['type' => 0, 'name' => 0],
    ]);
    $this->assertSame([], $this->presetArgument($build[0]));
    $this->assertArrayNotHasKey('exposed_filters', $build[0]);
  }

  /**
   * Tests that an enabled multi-value filter is read from the query.
   */
  public function testEnabledFilterIsReadFromQuery(): void {
    $this->setRequestQuery(['type' => ['bar']]);
    $build = $this->viewField($this->createHost(), [
      'exposed_filters' => ['type' => 'type', 'name' => 0],
    ]);

    $this->assertSame(['type' => ['bar']], $this->presetArgument($build[0]));
    // A value chosen by the visitor must not hide the filter.
    $this->assertArrayHasKey('exposed_filters', $build[0]);
    $this->assertNotFalse($build[0]['exposed_filters']['type']['#access'] ?? TRUE);

    $output = (string) $this->render($build);
    $this->assertStringContainsString('Bar item', $output);
    $this->assertStringNotContainsString('Foo item', $output);
  }

  /**
   * Tests that an enabled textfield filter is read from the query.
   */
  public function testEnabledTextfieldFilterIsReadFromQuery(): void {
    $this->setRequestQuery(['name' => 'Bar']);
    $build = $this->viewField($this->createHost(), [
      'exposed_filters' => ['name' => 'name'],
    ]);

    $this->assertSame(['name' => 'Bar'], $this->presetArgument($build[0]));
    $output = (string) $this->render($build);
    $this->assertStringContainsString('Bar item', $output);
    $this->assertStringNotContainsString('Foo item', $output);
  }

  /**
   * Tests the exposed form shown for enabled filters without a value.
   */
  public function testExposedForm(): void {
    $this->setRequestQuery([]);
    $build = $this->viewField($this->createHost(), [
      'exposed_filters' => ['type' => 'type', 'name' => 0, 'name_group' => '0'],
    ]);

    $form = $build[0]['exposed_filters'];
    $this->assertSame('views_exposed_form', $form['#form_id']);
    $this->assertSame('exposed_embed_1', $form['#display_id']);
    $this->assertNotFalse($form['type']['#access'] ?? TRUE);
    // The name filter exposes its operator, so it lives in a wrapper.
    $this->assertArrayHasKey('name', $form['name_wrapper']);
    $this->assertFalse($form['name_wrapper']['#access']);
    $this->assertFalse($form['name_group']['#access']);
  }

  /**
   * Tests that preset filters are hidden from the exposed form.
   */
  public function testPresetFiltersAreHiddenFromExposedForm(): void {
    $this->setRequestQuery([]);
    $build = $this->viewField($this->createHost(['type' => ['foo']]), [
      'exposed_filters' => ['type' => 'type', 'name' => 'name'],
    ]);

    $form = $build[0]['exposed_filters'];
    $this->assertFalse($form['type']['#access']);
    $this->assertNotFalse($form['name_wrapper']['#access'] ?? TRUE);
  }

  /**
   * Tests that the filter form is empty without enabled filters.
   */
  public function testCreateFilterFormWithoutSettings(): void {
    $formatter = $this->createFormatter();
    $method = new \ReflectionMethod($formatter, 'createFilterForm');
    $view = Views::getView(static::VIEW_ID);
    $view->setDisplay('exposed_embed_1');
    $this->assertSame([], $method->invoke($formatter, $view, []));
  }

  /**
   * Tests that query values are safely passed on and rendered.
   */
  public function testQueryValuesAreEscaped(): void {
    $payload = '"><script>alert("xss")</script>';
    $this->setRequestQuery(['name' => $payload]);
    $build = $this->viewField($this->createHost(), [
      'exposed_filters' => ['type' => 'type', 'name' => 'name'],
    ]);

    // The value is kept as data, but encoded so that it can not break out of
    // the JSON argument when it ends up in drupalSettings.
    $argument = end($build[0]['#arguments']);
    $this->assertStringNotContainsString('<script>', $argument);
    $this->assertStringNotContainsString('"><', $argument);
    $this->assertSame(['name' => $payload], $this->presetArgument($build[0]));

    $output = (string) $this->render($build);
    $this->assertStringNotContainsString('<script>alert("xss")</script>', $output);
  }

  /**
   * Tests that a missing view or display renders nothing instead of failing.
   */
  public function testMissingViewOrDisplay(): void {
    $this->setRequestQuery([]);
    $host = $this->createHost(['type' => ['foo']]);

    $this->field->setSetting('view_id', 'does_not_exist');
    $build = $this->createFormatter()->viewElements($host->get(static::FIELD_NAME), 'en');
    $this->assertSame([[]], $build);

    $this->field->setSetting('view_id', static::VIEW_ID);
    $this->field->setSetting('display_id', 'does_not_exist');
    $build = $this->createFormatter()->viewElements($host->get(static::FIELD_NAME), 'en');
    $this->assertSame([[]], $build);
  }

  /**
   * Tests that the display access settings are respected.
   */
  public function testAccessIsChecked(): void {
    $this->setRequestQuery([]);
    $this->field->setSetting('display_id', 'exposed_embed_restricted');
    $this->field->save();
    $host = $this->createHost(['type' => ['foo']]);

    $build = $this->viewField($host);
    $this->assertSame(['#cache' => ['contexts' => ['user.permissions', 'user.roles']]], $build[0]);
    $this->assertStringNotContainsString('Foo item', (string) $this->render($build));

    $this->setUpCurrentUser([], ['view test entity']);
    $host = $this->container->get('entity_type.manager')->getStorage('entity_test')->loadUnchanged($host->id());
    $build = $this->viewField($host);
    $this->assertStringContainsString('Foo item', (string) $this->render($build));
  }

}
