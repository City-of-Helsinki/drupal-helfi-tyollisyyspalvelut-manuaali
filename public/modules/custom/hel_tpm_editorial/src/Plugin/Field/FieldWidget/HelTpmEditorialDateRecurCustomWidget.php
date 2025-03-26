<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_editorial\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\date_recur\DateRecurPartGrid;
use Drupal\date_recur\DateRecurRuleInterface;
use Drupal\date_recur\Exception\DateRecurHelperArgumentException;
use Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem;
use Drupal\date_recur_modular\DateRecurModularWidgetFieldsTrait;
use Drupal\date_recur_modular\DateRecurModularWidgetOptions;
use Drupal\date_recur_modular\Plugin\Field\FieldWidget\DateRecurModularAlphaWidget;

/**
 * Hel Tpm Editorial date recur widget.
 *
 * This is a widget built with Drupal states in combination with light sprinkle
 * of CSS.
 *
 * It supports the following modes:
 *  - Non-recurring.
 *  - Multiday.
 *  - Weekly:
 *    - hard coded interval of 1. Or 2 if fortnightly is chosen.
 *    - with optional expansion to multiple weekdays.
 *    - with optional occurrence limitation by date or count.
 *  - Monthly:
 *    - hard coded interval of 1.
 *    - with optional expansion to multiple weekdays.
 *    - with optional limiter on ordinal.
 *    - with optional occurrence limitation by date or count.
 *
 * Frequencies and parts are designed to be inaccessible or temporarily
 * invisible or if field level frequency/part configuration dictate it.
 *
 * @FieldWidget(
 *   id = "hel_tpm_editorial_date_recur_custom",
 *   label = @Translation("Custom: Date recur formatter"),
 *   field_types = {
 *     "date_recur"
 *   }
 * )
 */
class HelTpmEditorialDateRecurCustomWidget extends DateRecurModularAlphaWidget {

  use DateRecurModularWidgetFieldsTrait;

  protected const MODE_ONCE = 'once';

  protected const MODE_MULTIDAY = 'multiday';

  protected const MODE_WEEKLY = 'weekly';

  protected const TIME_ZONE = 'Europe/Helsinki';

  /**
   * Part grid for this list.
   *
   * @var \Drupal\date_recur\DateRecurPartGrid
   */
  protected DateRecurPartGrid $partGrid;

  /**
   * {@inheritdoc}
   */
  protected function getModes(): array {
    return [
      static::MODE_ONCE => $this->t('Once'),
      static::MODE_MULTIDAY => $this->t('Multiple days'),
      static::MODE_WEEKLY => $this->t('Weekly'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getMode(DateRecurItem $item): ?string {
    try {
      $helper = $item->getHelper();
    }
    catch (DateRecurHelperArgumentException $e) {
      return NULL;
    }

    $rules = $helper->getRules();
    $rule = reset($rules);
    if (FALSE === $rule) {
      // This widget supports one RRULE per field value.
      return NULL;
    }

    $frequency = $rule->getFrequency();
    $parts = $rule->getParts();

    if ('DAILY' === $frequency) {
      /** @var int|null $count */
      $count = $parts['COUNT'] ?? NULL;
      return $count && $count > 1 ? static::MODE_MULTIDAY : static::MODE_ONCE;
    }
    elseif ('WEEKLY' === $frequency) {
      /** @var int|null $interval */
      $interval = $parts['INTERVAL'] ?? NULL;
      return [1 => static::MODE_WEEKLY, 2 => static::MODE_FORTNIGHTLY][$interval] ?? NULL;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    /** @var \Drupal\date_recur\Plugin\Field\FieldType\DateRecurFieldItemList|\Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem[] $items */
    $elementParents = array_merge($element['#field_parents'], [
      $this->fieldDefinition->getName(),
      $delta,
    ]);
    $element['#element_validate'][] = [static::class, 'validateModularWidget'];
    $element['#after_build'][] = [static::class, 'afterBuildModularWidget'];
    $element['#theme'] = 'hel_tpm_editorial_date_recur_custom_widget';
    $element['#theme_wrappers'][] = 'form_element';

    $item = $items[$delta];

    $grid = $items->getPartGrid();
    $rule = $this->getRule($item);
    $parts = $rule ? $rule->getParts() : [];
    $count = $parts['COUNT'] ?? NULL;
    $timeZone = $this->getDefaultTimeZone($item);
    $endsDate = NULL;
    try {
      $until = $parts['UNTIL'] ?? NULL;
      if (is_string($until)) {
        $endsDate = new \DateTime($until);
      }
      elseif ($until instanceof \DateTimeInterface) {
        $endsDate = $until;
      }
      if ($endsDate) {
        // UNTIL is _usually_ in UTC, adjust it to the field time zone.
        $endsDate->setTimezone(new \DateTimeZone($timeZone));
      }
    }
    catch (\Exception $e) {
    }

    $fieldModes = $this->getFieldModes($grid);

    $datetime_config = [
      '#date_date_element' => 'text',
      '#date_time_element' => 'text',
      '#date_date_format' => 'd.m.Y',
      '#date_time_format' => 'H:i:s',
      '#attached' => [
        'library' => ['hel_tpm_editorial/custom-datetimepicker'],
      ],
    ];

    $element['start'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Starts on'),
      '#title_display' => 'invisible',
      '#default_value' => $item->start_date,
      // \Drupal\Core\Datetime\Element\Datetime::valueCallback tries to change
      // the time zone to current users timezone if not set, Set the timezone
      // here so the value doesn't change.
      '#date_timezone' => $timeZone,
    ];

    $element['end'] = [
      '#title' => $this->t('Ends on'),
      '#title_display' => 'invisible',
      '#type' => 'datetime',
      '#default_value' => $item->end_date,
      '#date_timezone' => $timeZone,
    ];

    $element['start'] = array_merge($element['start'], $datetime_config);
    $element['end'] = array_merge($element['end'], $datetime_config);

    $element['mode'] = $this->getFieldMode($item);
    $element['mode']['#title_display'] = 'invisible';

    $element['daily_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Days'),
      '#title_display' => 'invisible',
      '#field_suffix' => $this->t('days'),
      '#default_value' => $count ?? 1,
      '#min' => 1,
      // Some part elements also need to check access, as when states are
      // applied if there are no conditions then the field is always visible.
      '#access' => count($fieldModes['daily_count'] ?? []) > 0,
    ];
    $element['daily_count']['#states'] = $this->getVisibilityStates($element, $fieldModes['daily_count'] ?? []);

    $element['weekdays'] = $this->getFieldByDay($rule);
    $element['weekdays']['#attributes']['class'][] = 'weekdays';

    foreach ($element['weekdays']['#options'] as $key => &$value) {
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $value = $this->t($key);
    }

    $element['weekdays']['#states'] = $this->getVisibilityStates($element, $fieldModes['weekdays'] ?? []);
    $element['ordinals'] = $this->getFieldMonthlyByDayOrdinals($element, $rule);
    $element['ordinals']['#states'] = $this->getVisibilityStates($element, $fieldModes['ordinals'] ?? []);
    // $element['ordinals']['#title_display'] = 'invisible';
    $endsModeDefault =
      $endsDate ? DateRecurModularWidgetOptions::ENDS_MODE_ON_DATE :
      ($count > 0 ? DateRecurModularWidgetOptions::ENDS_MODE_OCCURRENCES : DateRecurModularWidgetOptions::ENDS_MODE_INFINITE);
    $element['ends_mode'] = $this->getFieldEndsMode();
    $element['ends_mode']['#states'] = $this->getVisibilityStates($element, $fieldModes['ends_mode'] ?? []);
    $element['ends_mode']['#title_display'] = 'before';
    $element['ends_mode']['#default_value'] = $endsModeDefault;
    // Hide or show 'On date' / 'number of occurrences' checkboxes depending on
    // selected mode.
    $element['ends_mode'][DateRecurModularWidgetOptions::ENDS_MODE_OCCURRENCES]['#states'] = $this->getVisibilityStates($element, $fieldModes['ends_count'] ?? []);
    $element['ends_mode'][DateRecurModularWidgetOptions::ENDS_MODE_ON_DATE]['#states'] = $this->getVisibilityStates($element, $fieldModes['ends_date'] ?? []);

    $element['ends_count'] = [
      '#type' => 'number',
      '#title' => $this->t('End after number of occurrences'),
      '#title_display' => 'invisible',
      '#field_prefix' => $this->t('after'),
      '#field_suffix' => $this->t('occurrences'),
      '#default_value' => $count ?? 1,
      '#min' => 1,
      '#access' => count($fieldModes['ends_count'] ?? []) > 0,
    ];
    $nameMode = $this->getName($element, ['mode']);
    $nameEndsMode = $this->getName($element, ['ends_mode']);
    $element['ends_count']['#states']['visible'] = [];
    foreach ($fieldModes['ends_count'] ?? [] as $mode) {
      $element['ends_count']['#states']['visible'][] = [
        ':input[name="' . $nameMode . '"]' => ['value' => $mode],
        ':input[name="' . $nameEndsMode . '"]' => ['value' => DateRecurModularWidgetOptions::ENDS_MODE_OCCURRENCES],
      ];
    }

    // States don't yet work on date time so put it in a container.
    // @see https://www.drupal.org/project/drupal/issues/2419131
    $element['ends_date'] = [
      '#type' => 'container',
    ];
    $element['ends_date']['ends_date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('End before this date'),
      '#description' => $this->t('No occurrences can begin after this date.'),
      '#default_value' => $endsDate ? DrupalDateTime::createFromDateTime($endsDate) : NULL,
      // Fix values tree thanks to state+container hack.
      '#parents' => array_merge($elementParents, ['ends_date']),
      // \Drupal\Core\Datetime\Element\Datetime::valueCallback tries to change
      // the time zone to current users timezone if not set, Set the timezone
      // here so the value doesn't change.
      '#date_timezone' => $this::TIME_ZONE,
    ];
    $element['ends_date']['ends_date']['#attributes']['class'][] = 'ends-date-item';
    $element['ends_date']['ends_date'] = array_merge($element['ends_date']['ends_date'], $datetime_config);
    $element['ends_date']['#states']['visible'] = [];
    foreach ($fieldModes['ends_date'] ?? [] as $mode) {
      $element['ends_date']['#states']['visible'][] = [
        ':input[name="' . $nameMode . '"]' => ['value' => $mode],
        ':input[name="' . $nameEndsMode . '"]' => ['value' => DateRecurModularWidgetOptions::ENDS_MODE_ON_DATE],
      ];
    }

    // Preview is currently not needed so code is commented out for later use.
    /*
    $wrapper = 'date-preview-wrapper-' . implode('-', $elementParents);;
    $element['preview'] = [
    '#type' => 'button',
    '#value' => $this->t('Preview date'),
    '#ajax' => [
    'callback' => [$this, 'previewDate'],
    'wrapper' => $wrapper,
    'event' => 'click'
    ],
    '#limit_validation_errors' => [],
    '#name' => Html::cleanCssIdentifier(implode('-', array_merge(
    $elementParents, ['preview']
    ))),
    ];
    $element['preview_element'] = [
    '#type' => 'html_tag',
    '#tag' => 'div',
    '#attributes' => ['id' => $wrapper]
    ];*/

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldByDay(?DateRecurRuleInterface $rule, string $weekDayLabels = 'full'): array {
    $element = parent::getFieldByDay($rule, $weekDayLabels);
    $element['#title_display'] = 'visible';
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $parents = $form['#parents'];

    // Determine the number of widgets to display.
    switch ($cardinality) {
      case FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED:
        $field_state = static::getWidgetState($parents, $field_name, $form_state);
        $max = $field_state['items_count'];
        $is_multiple = TRUE;
        // If max is 0 and there is no items.
        // Append new item to items object and update field state items count.
        // This fixes empty row instance appearing when editing and entity.
        if ($max <= 0 && $items->count() == 0) {
          $items->appendItem();
          $max = $items->count();
          $field_state['items_count'] = $max;
          static::setWidgetState($parents, $field_name, $form_state, $field_state);
        }
        break;

      default:
        $max = $cardinality - 1;
        $is_multiple = ($cardinality > 1);
        break;
    }

    $title = $this->fieldDefinition->getLabel();
    $description = $this->getFilteredDescription();

    $elements = [];

    for ($delta = 0; $delta < $max; $delta++) {
      // Add a new empty item if it doesn't exist yet at this delta.
      if (!isset($items[$delta])) {
        $items->appendItem();
      }

      // For multiple fields, title and description are handled by the wrapping
      // table.
      if ($is_multiple) {
        $element = [
          '#title' => $this->t('@title (value @number)', [
            '@title' => $title,
            '@number' => $delta + 1,
          ]),
          '#title_display' => 'invisible',
          '#description' => '',
        ];
      }
      else {
        $element = [
          '#title' => $title,
          '#title_display' => 'before',
          '#description' => $description,
        ];
      }

      $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);

      if ($element) {
        // Input field for the delta (drag-n-drop reordering).
        if ($is_multiple) {
          // We name the element '_weight' to avoid clashing with elements
          // defined by widget.
          $element['_weight'] = [
            '#type' => 'weight',
            '#title' => $this->t('Weight for row @number', ['@number' => $delta + 1]),
            '#title_display' => 'invisible',
            // Note: this 'delta' is the FAPI #type 'weight' element's property.
            '#delta' => $max,
            '#default_value' => $items[$delta]->_weight ?: $delta,
            '#weight' => 100,
          ];
        }

        $elements[$delta] = $element;
      }
    }

    if ($elements) {
      $elements += [
        '#theme' => 'field_multiple_value_form',
        '#field_name' => $field_name,
        '#cardinality' => $cardinality,
        '#cardinality_multiple' => $this->fieldDefinition->getFieldStorageDefinition()->isMultiple(),
        '#required' => $this->fieldDefinition->isRequired(),
        '#title' => $title,
        '#description' => $description,
        '#max_delta' => $max,
      ];

      // Add 'add more' button, if not working with a programmed form.
      if ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED && !$form_state->isProgrammed()) {
        $id_prefix = implode('-', array_merge($parents, [$field_name]));
        $wrapper_id = Html::getUniqueId($id_prefix . '-add-more-wrapper');
        $elements['#prefix'] = '<div id="' . $wrapper_id . '">';
        $elements['#suffix'] = '</div>';

        $elements['add_more'] = [
          '#type' => 'submit',
          '#name' => strtr($id_prefix, '-', '_') . '_add_more',
          '#value' => $this->t('Add another date'),
          '#attributes' => ['class' => ['field-add-more-submit']],
          '#limit_validation_errors' => [array_merge($parents, [$field_name])],
          '#submit' => [[static::class, 'addMoreSubmit']],
          '#ajax' => [
            'callback' => [static::class, 'addMoreAjax'],
            'wrapper' => $wrapper_id,
            'effect' => 'fade',
          ],
        ];
        self::addRemoveRowButton($elements, $id_prefix, $wrapper_id, $max);
      }
    }

    return $elements;
  }

  /**
   * Helper method to add remove row button.
   *
   * {@inheritdoc}
   */
  public function addRemoveRowButton(&$elements, $id_prefix, $wrapper_id, $max_delta): void {
    for ($delta = 0; $delta < $max_delta; $delta++) {
      if (empty($elements[$delta])) {
        return;
      }
      $id_prefix = sprintf('%s-row-%s', $id_prefix, $delta);
      $element = &$elements[$delta];
      $remove_button = [
        '#delta' => $delta,
        '#name' => str_replace('-', '_', $id_prefix) . "_{$delta}_add_more_remove_button",
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#validate' => [],
        '#submit' => [[static::class, 'deleteSubmit']],
        '#limit_validation_errors' => [],
        '#attributes' => [
          'class' => ['remove-field-delta--' . $delta],
        ],
        '#ajax' => [
          'callback' => [static::class, 'deleteAjax'],
          'wrapper' => $wrapper_id,
          'effect' => 'fade',
        ],
      ];

      $element['_actions'] = [
        'remove_button' => $remove_button,
        '#weight' => 101,
      ];
    }
  }

  /**
   * Submission handler for the "Add another item" button.
   */
  public static function addMoreSubmit(array $form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();

    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $field_name = $element['#field_name'];
    $parents = $element['#field_parents'];

    // Increment the items count.
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    $field_state['items_count']++;
    static::setWidgetState($parents, $field_name, $form_state, $field_state);

    $form_state->setRebuild();
  }

  /**
   * Preview date ajax submit.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface.
   *
   * @return array
   *   Return preview date element.
   */
  public function previewDate(array $form, FormStateInterface &$form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#parents'];
    $input = $form_state->getUserInput();
    $items = $form_state->getBuildInfo()['args']['items'];
    $this->partGrid = $items->getPartGrid();

    foreach ($parents as $key) {
      if (!empty($input[$key])) {
        $input = $input[$key];
      }
    }

    $input['start'] = $this->createDrupalDateTime($input['start']);
    $input['end'] = $this->createDrupalDateTime($input['end']);
    $input['time_zone'] = self::TIME_ZONE;
    $values = $this->massageFormValues([$input], $form, $form_state);

    $items->setValue($values);

    $element['preview_element'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['id' => $triggering_element['#ajax']['wrapper']],
    ];

    $element['preview_element']['element'] = $items->get(0)->view();

    return $element;
  }

  /**
   * Validates the widget.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateModularWidget(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    // Each of these values can be array if input was invalid, e.g. date or time
    // is not provided.
    /** @var \Drupal\Core\Datetime\DrupalDateTime|array|null $start */
    $start = $form_state->getValue(array_merge($element['#parents'], ['start']));
    /** @var \Drupal\Core\Datetime\DrupalDateTime|array|null $end */
    $end = $form_state->getValue(array_merge($element['#parents'], ['end']));
    /** @var string|null $timeZone */
    $timeZone = self::TIME_ZONE;

    // Check timezone.
    if ($start && !$timeZone) {
      $form_state->setError($element['start'], t('Time zone must be set if start date is set.'));
    }
    if ($end && !$timeZone) {
      $form_state->setError($element['end'], t('Time zone must be set if end date is set.'));
    }

    if (
      ($start instanceof DrupalDateTime || $end instanceof DrupalDateTime)
      && (!$start instanceof DrupalDateTime || !$end instanceof DrupalDateTime)
    ) {
      $form_state->setError($element, t('Start date and end date must be provided.'));
      return;
    }

    if ($start instanceof DrupalDateTime && $end instanceof DrupalDateTime) {
      if ($start->getTimestamp() > $end->getTimestamp()) {
        $form_state->setError($element['start'], t('Start date must be greater than end date.'));
      }
    }

    // Recreate datetime object with exactly the same date and time but
    // different timezone.
    $zoneLess = 'Y-m-d H:i:s';
    $timeZoneObj = new \DateTimeZone($timeZone);
    if ($start instanceof DrupalDateTime && $timeZone) {
      $start = DrupalDateTime::createFromFormat($zoneLess, $start->format($zoneLess), $timeZoneObj);
      $form_state->setValueForElement($element['start'], $start);
    }
    if ($end instanceof DrupalDateTime && $timeZone) {
      $end = DrupalDateTime::createFromFormat($zoneLess, $end->format($zoneLess), $timeZoneObj);
      $form_state->setValueForElement($element['end'], $end);
    }
    /** @var \Drupal\Core\Datetime\DrupalDateTime|array|null $endsDate */
    $endsDate = $form_state->getValue(array_merge($element['#parents'], ['ends_date']));
    if ($endsDate instanceof DrupalDateTime && $timeZone) {
      $endsDate = DrupalDateTime::createFromFormat($zoneLess, $endsDate->format($zoneLess), $timeZoneObj);
      $form_state->setValueForElement($element['ends_date'], $endsDate);
    }
  }

  /**
   * After build callback for the widget.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The element.
   */
  public static function afterBuildModularWidget(array $element, FormStateInterface $form_state): array {
    // Wait until ID is created, and after
    // \Drupal\Core\Render\Element\Checkboxes::processCheckboxes is run so
    // states are not replicated to children.
    $weekdaysId = $element['weekdays']['#id'];
    $element['ordinals']['#states']['visible'][0]['#' . $weekdaysId . ' input[type="checkbox"]'] = ['checked' => TRUE];
    // Add container classes to compact checkboxes.
    $element['weekdays']['#attributes']['class'][] = 'container-inline';
    $element['ordinals']['#attributes']['class'][] = 'container-inline';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\date_recur\Plugin\Field\FieldType\DateRecurFieldItemList $items */
    $this->partGrid = $items->getPartGrid();
    parent::extractFormValues(...func_get_args());
    unset($this->partGrid);
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $dateStorageFormat = $this->fieldDefinition->getSetting('datetime_type') == DateRecurItem::DATETIME_TYPE_DATE ? DateRecurItem::DATE_STORAGE_FORMAT : DateRecurItem::DATETIME_STORAGE_FORMAT;
    $dateStorageTimeZone = new \DateTimezone(DateRecurItem::STORAGE_TIMEZONE);
    $grid = $this->partGrid;

    $returnValues = [];
    foreach ($values as $value) {
      // Call to parent invalidates and empties individual values.
      if (empty($value)) {
        continue;
      }

      $item = [];

      $start = $value['start'] ?? NULL;
      if (is_array($start)) {
        $start = $this->createDrupalDateTime($start);
      }
      if (empty($start)) {
        continue;
      }
      assert(!isset($start) || $start instanceof DrupalDateTime);
      $end = $value['end'] ?? NULL;
      if (is_array($end)) {
        $end = $this->createDrupalDateTime($end);
      }
      if (empty($end)) {
        continue;
      }
      assert(!isset($end) || $end instanceof DrupalDateTime);
      $timeZone = self::TIME_ZONE;
      assert(is_string($timeZone));
      $mode = $value['mode'] ?? NULL;
      $endsMode = $value['ends_mode'] ?? NULL;
      /** @var \Drupal\Core\Datetime\DrupalDateTime|array|null $endsDate */
      $endsDate = $value['ends_date'] ?? NULL;

      // Adjust the date for storage.
      $start->setTimezone($dateStorageTimeZone);
      $item['value'] = $start->format($dateStorageFormat);
      $end->setTimezone($dateStorageTimeZone);
      $item['end_value'] = $end->format($dateStorageFormat);
      $item['timezone'] = $timeZone;

      $weekDays = array_values(array_filter($value['weekdays']));
      $byDayStr = implode(',', $weekDays);

      $rule = [];
      if ($mode === static::MODE_MULTIDAY) {
        $rule['FREQ'] = 'DAILY';
        $rule['INTERVAL'] = 1;
        $rule['COUNT'] = $value['daily_count'];
      }
      elseif ($mode === static::MODE_WEEKLY) {
        $rule['FREQ'] = 'WEEKLY';
        $rule['INTERVAL'] = 1;
        $rule['BYDAY'] = $byDayStr;
      }
      // Ends mode.
      if ($endsMode === DateRecurModularWidgetOptions::ENDS_MODE_OCCURRENCES && $mode !== static::MODE_MULTIDAY) {
        $rule['COUNT'] = (int) $value['ends_count'];
      }
      elseif ($endsMode === DateRecurModularWidgetOptions::ENDS_MODE_ON_DATE && $endsDate instanceof DrupalDateTime) {
        $endsDateUtcAdjusted = (clone $endsDate)
          ->setTimezone(new \DateTimeZone('UTC'));
        $rule['UNTIL'] = $endsDateUtcAdjusted->format('Ymd\THis\Z');
      }

      if (isset($rule['FREQ'])) {
        $rule = array_filter($rule);
        $item['rrule'] = $this->buildRruleString($rule, $grid);
      }

      $returnValues[] = $item;
    }

    return $returnValues;
  }

  /**
   * Create DrupalDateTime element from given date.
   *
   * @param array $date
   *   Date array.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   DrupalDateTime object or null.
   */
  protected function createDrupalDateTime(array $date): ?DrupalDateTime {
    if (!$timestamp = strtotime(implode($date))) {
      return NULL;
    }
    return DrupalDateTime::createFromTimestamp($timestamp);
  }

}
