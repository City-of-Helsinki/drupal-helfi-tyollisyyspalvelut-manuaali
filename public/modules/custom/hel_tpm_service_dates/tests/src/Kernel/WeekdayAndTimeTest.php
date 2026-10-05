<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_service_dates\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\hel_tpm_service_dates\WeekdayAndTimeValue;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests scalar time storage, editing, rendering, and legacy database updates.
 */
#[Group('hel_tpm_service_dates')]
final class WeekdayAndTimeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'file', 'language',
    'entity_reference_revisions', 'paragraphs', 'hel_tpm_service_dates',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['system']);
    ConfigurableLanguage::createFromLangcode('fi')->save();
    ParagraphsType::create(['id' => 'schedule', 'label' => 'Schedule'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'paragraph',
      'field_name' => 'field_weekday_and_time',
      'type' => 'hel_tpm_service_dates_weekday_and_time_field',
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'paragraph',
      'bundle' => 'schedule',
      'field_name' => 'field_weekday_and_time',
      'translatable' => TRUE,
    ])->save();
    $this->container->get('module_handler')->loadInclude('hel_tpm_service_dates', 'install');
  }

  /**
   * Supplies two time slots, including midnight and non-zero seconds.
   */
  private function schedule(): array {
    return [
      'monday' => [
        ['selector' => 1, 'time' => ['start' => '00:00:00', 'end' => '11:30:45']],
        ['selector' => 1, 'time' => ['start' => '13:00:00', 'end' => '17:15:00']],
      ],
    ];
  }

  /**
   * Creates a widget for the schedule field.
   */
  private function widget(Paragraph $paragraph): mixed {
    return $this->container->get('plugin.manager.field.widget')->getInstance([
      'field_definition' => $paragraph->getFieldDefinition('field_weekday_and_time'),
      'configuration' => ['type' => 'hel_tpm_service_dates_weekday_and_time_field', 'settings' => []],
    ]);
  }

  /**
   * Tests normal and failed-validation form values, storage, and editing.
   */
  public function testSaveEditAndRender(): void {
    $paragraph = Paragraph::create(['type' => 'schedule']);
    $widget = $this->widget($paragraph);
    $schedule = $this->schedule();
    foreach ($schedule['monday'] as &$row) {
      $row['time']['start'] = new DrupalDateTime('2026-07-01 ' . $row['time']['start'], 'Europe/Helsinki');
      $row['time']['end'] = [
        'object' => new DrupalDateTime('2026-07-01 ' . $row['time']['end'], 'Europe/Helsinki'),
        'time' => 'ignored',
      ];
    }
    unset($row);
    $schedule['tuesday'] = [['selector' => 0, 'time' => ['start' => NULL, 'end' => NULL]]];
    $state = new FormState();
    $values = $widget->massageFormValues([['value' => $schedule]], [], $state);
    $this->assertSame([['value' => $this->schedule()]], $values);
    $paragraph->field_weekday_and_time = $values;
    $paragraph->save();
    $this->assertStoredScalars();
    $this->container->get('entity_type.manager')->getStorage('paragraph')->resetCache();
    $paragraph = Paragraph::load($paragraph->id());
    $this->assertSame($values, $paragraph->field_weekday_and_time->getValue());
    $form = [];
    $element = $widget->formElement($paragraph->field_weekday_and_time, 0, ['#field_parents' => []], $form, $state);
    foreach ($this->schedule()['monday'] as $delta => $row) {
      foreach ($row['time'] as $key => $time) {
        $default = $element['value']['monday'][$delta]['time'][$key]['#default_value'];
        $this->assertInstanceOf(DrupalDateTime::class, $default);
        $this->assertSame($time, $default->format('H:i:s'));
      }
    }
    $formatter = $this->container->get('plugin.manager.field.formatter')->getInstance([
      'field_definition' => $paragraph->getFieldDefinition('field_weekday_and_time'),
      'view_mode' => 'default',
      'configuration' => [
        'type' => 'hel_tpm_service_dates_weekday_and_time_field_default',
        'label' => 'hidden',
        'settings' => [],
      ],
    ]);
    // A second delta must not repeat the first delta's markup.
    $paragraph->field_weekday_and_time->appendItem(['value' => ['tuesday' => [$this->schedule()['monday'][0]]]]);
    $elements = $formatter->viewElements($paragraph->field_weekday_and_time, 'en');
    $this->assertSame('Mon at 00:00 - 11:30 and 13:00 - 17:15', (string) $elements[0]['items'][0]['#markup']);
    $this->assertCount(1, $elements[1]['items']);
    $this->assertSame('Tue at 00:00 - 11:30', (string) $elements[1]['items'][0]['#markup']);
    // Programmatic entity writes must normalize objects as well.
    unset($schedule['tuesday']);
    $paragraph->field_weekday_and_time = [['value' => $schedule]];
    $paragraph->save();
    $this->assertStoredScalars();
  }

  /**
   * Asserts database payloads contain only arrays and scalar values.
   */
  private function assertStoredScalars(): void {
    foreach (['paragraph__field_weekday_and_time', 'paragraph_revision__field_weekday_and_time'] as $table) {
      foreach ($this->container->get('database')->select($table, 't')->fields('t', ['field_weekday_and_time_value'])->execute()->fetchCol() as $payload) {
        $this->assertSame($this->schedule(), unserialize($payload, [
          'allowed_classes' => [DrupalDateTime::class, \DateTime::class],
        ]));
        $this->assertStringNotContainsString('O:', $payload);
      }
    }
  }

  /**
   * Tests batched migration of revisions, languages, deltas, and deleted rows.
   */
  public function testUpdate(): void {
    $paragraph = Paragraph::create([
      'type' => 'schedule',
      'field_weekday_and_time' => [['value' => $this->schedule()]],
    ]);
    $paragraph->addTranslation('fi', [
      'field_weekday_and_time' => [
        ['value' => $this->schedule()],
        ['value' => $this->schedule()],
      ],
    ]);
    $paragraph->save();
    $old_revision_id = $paragraph->getRevisionId();
    $paragraph->setNewRevision(TRUE);
    $paragraph->save();
    $schedule = $this->schedule();
    foreach ($schedule['monday'] as &$row) {
      foreach ($row['time'] as &$time) {
        $time = new DrupalDateTime('2020-07-01 ' . $time, 'Europe/Helsinki');
      }
      unset($time);
    }
    unset($row);
    // Inject a removed property into serialized objects without creating it on
    // current PHP. This models data written by older versions of Drupal.
    $legacy = preg_replace_callback('/O:([0-9]+):"Drupal\\\\Core\\\\Datetime\\\\DrupalDateTime":([0-9]+):\{/', static function ($match) {
      return 'O:' . $match[1] . ':"Drupal\\Core\\Datetime\\DrupalDateTime":' . ((int) $match[2] + 1) . ':{s:12:"inputTimeRaw";s:5:"09:00";';
    }, serialize($schedule));
    $this->assertStringContainsString('inputTimeRaw', $legacy);
    $warnings = [];
    set_error_handler(static function ($severity, $message) use (&$warnings) {
      $warnings[] = $message;
      return TRUE;
    }, E_DEPRECATED);
    try {
      unserialize($legacy, ['allowed_classes' => [DrupalDateTime::class, \DateTime::class]]);
    }
    finally {
      restore_error_handler();
    }
    $this->assertStringContainsString('Creation of dynamic property Drupal\\Core\\Datetime\\DrupalDateTime::$inputTimeRaw is deprecated', implode("\n", $warnings));
    $database = $this->container->get('database');
    $tables = ['paragraph__field_weekday_and_time', 'paragraph_revision__field_weekday_and_time'];
    foreach ($tables as $table) {
      $database->update($table)->fields(['field_weekday_and_time_value' => $legacy])->execute();
    }
    // Exercise more than one batch and rows retained for field deletion.
    $record = (array) $database->select($tables[1], 't')->fields('t')->range(0, 1)->execute()->fetchObject();
    for ($delta = 2; $delta < 105; $delta++) {
      $record['delta'] = $delta;
      $record['deleted'] = 1;
      $database->insert($tables[1])->fields($record)->execute();
    }
    $counts = [];
    foreach ($tables as $table) {
      $counts[$table] = $database->select($table)->countQuery()->execute()->fetchField();
    }
    set_error_handler(static function ($severity, $message, $file, $line) {
      throw new \ErrorException($message, 0, $severity, $file, $line);
    }, E_DEPRECATED);
    try {
      $sandbox = [];
      $calls = 0;
      do {
        hel_tpm_service_dates_update_11001($sandbox);
        $this->assertLessThan(10, ++$calls);
      } while ($sandbox['#finished'] !== 1);
      $this->assertGreaterThan(2, $calls);
      $this->assertStoredScalars();
      // Running the update again leaves already migrated values unchanged.
      $sandbox = [];
      do {
        hel_tpm_service_dates_update_11001($sandbox);
      } while ($sandbox['#finished'] !== 1);
      $this->assertStoredScalars();
      $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
      $storage->resetCache();
      $loaded = $storage->load($paragraph->id());
      $this->assertSame($this->schedule(), $loaded->field_weekday_and_time->value);
      $this->assertCount(2, $loaded->getTranslation('fi')->field_weekday_and_time);
      $old = $storage->loadRevision($old_revision_id);
      $this->assertSame($this->schedule(), $old->field_weekday_and_time->value);
    }
    finally {
      restore_error_handler();
    }
    foreach ($tables as $table) {
      $this->assertEquals($counts[$table], $database->select($table)->countQuery()->execute()->fetchField());
    }
  }

  /**
   * Tests that required times and start/end ordering are still validated.
   */
  public function testTimeValidation(): void {
    $widget = $this->widget(Paragraph::create(['type' => 'schedule']));
    $form = [];
    $element = [
      '#parents' => ['schedule', 'time'],
      'start' => [
        '#parents' => ['schedule', 'time', 'start'],
        '#value' => ['object' => new DrupalDateTime('2026-07-01 09:00')],
      ],
      'end' => [
        '#parents' => ['schedule', 'time', 'end'],
        '#value' => ['object' => new DrupalDateTime('2026-07-01 17:00')],
      ],
    ];
    $state = (new FormState())->setValues(['schedule' => ['selector' => 1]]);
    $widget->validateTimeSelection($element, $state, $form);
    $this->assertSame([], $state->getErrors());
    $element['end']['#value']['object'] = new DrupalDateTime('2026-07-01 08:00');
    $widget->validateTimeSelection($element, $state, $form);
    $this->assertArrayHasKey('schedule][time', $state->getErrors());
    $state->clearErrors();
    $element['end']['#value']['object'] = NULL;
    $widget->validateTimeSelection($element, $state, $form);
    $this->assertArrayHasKey('schedule][time][end', $state->getErrors());
    $state->clearErrors();
    $state->setValues(['schedule' => ['selector' => 0]]);
    $widget->validateTimeSelection($element, $state, $form);
    $this->assertSame([], $state->getErrors());
  }

  /**
   * Tests optional slots, empty values, and AJAX clearing.
   */
  public function testEmptyAndUnselectedValues(): void {
    $paragraph = Paragraph::create(['type' => 'schedule']);
    $widget = $this->widget($paragraph);
    $schedule = $this->schedule();
    $schedule['monday'][1]['selector'] = 0;
    $values = $widget->massageFormValues([['value' => $schedule]], [], new FormState());
    $this->assertCount(1, $values[0]['value']['monday']);
    $this->assertNull(WeekdayAndTimeValue::time(['object' => NULL, 'time' => '']));
    $this->assertSame([], WeekdayAndTimeValue::normalize([]));
    $state = new FormState();
    $trigger = ['#parents' => ['field_date_selection'], '#return_value' => 'other'];
    $state->setTriggeringElement($trigger);
    $this->assertSame(['value' => NULL], $widget->massageFormValues($values, [], $state));
  }

}
