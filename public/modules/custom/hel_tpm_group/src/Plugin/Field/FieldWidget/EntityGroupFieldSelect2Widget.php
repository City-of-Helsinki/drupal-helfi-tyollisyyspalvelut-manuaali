<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entitygroupfield\Plugin\Field\FieldWidget\EntityGroupFieldSelectWidget;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;

/**
 * Select2 widget for selecting the single group of an entity.
 *
 * The entity group field holds the group relationships of the entity. The
 * widget keeps the existing relationship when its group stays selected,
 * creates a new relationship for a newly selected group and drops the other
 * relationships, which the field deletes when the entity is saved.
 *
 * Entities may still have multiple groups from before the widget allowed only
 * one. Such an entity has no preselected group, so a group has to be selected
 * on the next save.
 *
 * @FieldWidget(
 *   id = "hel_tpm_group_entitygroupfield_select2",
 *   label = @Translation("Hel Group field Select2 (single group)"),
 *   field_types = {
 *     "entitygroupfield"
 *   },
 *   multiple_values = TRUE
 * )
 */
class EntityGroupFieldSelect2Widget extends EntityGroupFieldSelectWidget {

  /**
   * Permission required for editing the field.
   */
  public const PERMISSION = 'assign content to groups';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    unset($settings['multiple']);
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    unset($elements['multiple']);
    $elements['label']['#description'] = $this->t('The label of the group selection. Defaults to the field label.');
    $elements['help_text']['#description'] = $this->t('Instructions to present to the user below the group selection. Defaults to the field description.');
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->getSetting('required') ? $this->t('Required: Yes') : $this->t('Required: No');
    if ($label = $this->getSetting('label')) {
      $summary[] = $this->t('Label: %label', ['%label' => $label]);
    }
    if ($help_text = $this->getSetting('help_text')) {
      $summary[] = $this->t('Help text: %text', ['%text' => $help_text]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $parents = $element['#field_parents'];
    $state = static::getWidgetState($parents, $field_name, $form_state);

    // Relationships of the entity, keyed by group ID. Kept in the widget state
    // because the form entity isn't updated when the form is rebuilt.
    if (!isset($state['relationships'])) {
      $state['relationships'] = [];
      foreach ($items as $item) {
        if ($item->entity instanceof GroupRelationshipInterface && $item->entity->getGroupId()) {
          $state['relationships'][$item->entity->getGroupId()] = $item->entity;
        }
      }
    }
    $state['entity_plugin_id'] = $this->getEntityPluginId($items);
    $state['target_type'] = $this->getFieldSetting('target_type');
    $current_groups = $this->getRelationshipGroups($state['relationships']);

    // The group is selected before the form when creating the entity in a
    // group, and the group module adds the entity to it after saving.
    $route_group = $form_state->get('group_wizard_id') ? $form_state->get('group') : NULL;
    if ($route_group instanceof GroupInterface) {
      $current_groups = [$route_group->id() => $route_group];
    }

    $state['editable'] = $route_group === NULL
      && $state['entity_plugin_id']
      && $this->currentUser->hasPermission(self::PERMISSION);

    $options = [];
    if ($state['editable']) {
      $options = $this->getAllowedGroups($state['entity_plugin_id'], []);
    }
    // Current groups are valid options even when the user couldn't add the
    // entity to them anymore.
    foreach ($current_groups as $group) {
      if (!$this->optionExists($options, $group->id())) {
        $options[$group->getGroupType()->label()][$group->id()] = $this->entityRepository->getTranslationFromContext($group)->label();
      }
    }
    $options = $this->sortOptions($options);

    if (!isset($state['selected'])) {
      $state['selected'] = array_keys($current_groups);
      if ($route_group !== NULL) {
        // The relationship to the route group isn't created by this widget.
        $state['selected'] = [];
      }
    }
    $selected = $route_group !== NULL ? array_keys($current_groups) : $state['selected'];

    // Select the only option by default.
    $flat_options = $this->flattenOptions($options);
    if ($state['editable'] && empty($selected) && count($flat_options) === 1) {
      $selected = [array_key_first($flat_options)];
    }

    $element += [
      '#type' => 'select2',
      '#options' => $options,
      '#select2' => ['width' => '100%'],
      '#element_validate' => [[static::class, 'validateSelection']],
    ];
    if ($label = $this->getSetting('label')) {
      $element['#title'] = $label;
    }
    if ($help_text = $this->getSetting('help_text')) {
      $element['#description'] = $help_text;
    }

    if ($state['editable']) {
      $element['#required'] = (bool) $this->getSetting('required');
      $element['#multiple'] = FALSE;
      $element['#empty_option'] = $this->t('- Select a group -');
      $element['#default_value'] = count($selected) === 1 ? reset($selected) : NULL;
      if (count($selected) > 1) {
        $element['#description'] = [
          'help_text' => ['#markup' => $element['#description'] ?? ''],
          'multiple_groups' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t('The content belongs to multiple groups: %groups. Select the group it belongs to.', [
              '%groups' => implode(', ', array_intersect_key($flat_options, array_flip($selected))),
            ]),
          ],
        ];
      }
    }
    else {
      // Show the current groups to users who can't change them.
      $element['#disabled'] = TRUE;
      $element['#required'] = FALSE;
      $element['#multiple'] = count($selected) > 1;
      $element['#default_value'] = $element['#multiple'] ? $selected : (reset($selected) ?: NULL);
    }

    static::setWidgetState($parents, $field_name, $form_state, $state);
    return $element;
  }

  /**
   * Element validate callback storing the selected group in the widget state.
   *
   * The state keeps the selection when the form is rebuilt.
   */
  public static function validateSelection(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $state = static::getWidgetState($element['#field_parents'], $element['#field_name'], $form_state);
    if (empty($state['editable'])) {
      return;
    }
    $value = $element['#value'] ?? NULL;
    $state['selected'] = ($value === NULL || $value === '') ? [] : [$value];
    static::setWidgetState($element['#field_parents'], $element['#field_name'], $form_state, $state);
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $state = static::getWidgetState($form['#parents'], $this->fieldDefinition->getName(), $form_state);
    // Keep the groups as they are if the user can't change them.
    if (empty($state['editable'])) {
      return;
    }
    // The single value select submits a string, but the values are massaged
    // as a list.
    $path = array_merge($form['#parents'], [$this->fieldDefinition->getName()]);
    $value = NestedArray::getValue($form_state->getValues(), $path, $key_exists);
    if ($key_exists && !is_array($value)) {
      $form_state->setValue($path, ($value === NULL || $value === '') ? [] : [$value]);
    }
    parent::extractFormValues($items, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $state = static::getWidgetState($form['#parents'], $this->fieldDefinition->getName(), $form_state);
    $state['selected'] = array_slice(array_filter($values), 0, 1);

    $items = [];
    foreach (static::getSelectedRelationships($state) as $relationship) {
      $item = ['entity' => $relationship, 'target_id' => $relationship->id()];
      if ($relationship->isNew()) {
        $item['needs_save'] = TRUE;
      }
      $items[] = $item;
    }
    return $items;
  }

  /**
   * Gets the relationships of the groups selected in the widget.
   *
   * @param array $state
   *   The widget state.
   *
   * @return \Drupal\group\Entity\GroupRelationshipInterface[]
   *   Existing relationships of the selected groups, and new unsaved ones for
   *   newly selected groups.
   */
  public static function getSelectedRelationships(array $state): array {
    $relationships = [];
    foreach ($state['selected'] ?? [] as $group_id) {
      if (isset($state['relationships'][$group_id])) {
        $relationships[] = $state['relationships'][$group_id];
        continue;
      }
      if (empty($state['entity_plugin_id']) || empty($state['target_type'])) {
        continue;
      }
      $entity_type_manager = \Drupal::entityTypeManager();
      $group = $entity_type_manager->getStorage('group')->load($group_id);
      if (!$group instanceof GroupInterface) {
        continue;
      }
      /** @var \Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface $type_storage */
      $type_storage = $entity_type_manager->getStorage(entitygroupfield_get_group_relationship_type_id());
      $relationship_type_id = $type_storage->getRelationshipTypeId($group->bundle(), $state['entity_plugin_id']);
      $bundle_key = $entity_type_manager->getDefinition($state['target_type'])->getKey('bundle');
      $relationships[] = $entity_type_manager->getStorage($state['target_type'])->create([
        $bundle_key => $relationship_type_id,
        'gid' => $group->id(),
      ]);
    }
    return $relationships;
  }

  /**
   * Gets the group relation plugin ID of the entity.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   *
   * @return string|null
   *   The plugin ID, or NULL if the entity can't be added to groups.
   */
  protected function getEntityPluginId(FieldItemListInterface $items): ?string {
    $entity = $items->getEntity();
    $plugin_ids = entitygroupfield_get_group_relation_type_plugin_ids($entity->getEntityTypeId(), $entity->bundle());
    return $plugin_ids ? reset($plugin_ids) : NULL;
  }

  /**
   * Gets the groups of relationships.
   *
   * @param \Drupal\group\Entity\GroupRelationshipInterface[] $relationships
   *   The relationships.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   The groups keyed by group ID.
   */
  protected function getRelationshipGroups(array $relationships): array {
    $groups = [];
    foreach ($relationships as $relationship) {
      $group = $relationship->getGroup();
      if ($group instanceof GroupInterface) {
        $groups[$group->id()] = $group;
      }
    }
    return $groups;
  }

  /**
   * Checks if a group is in the options grouped by group type.
   */
  protected function optionExists(array $options, int|string $group_id): bool {
    return array_key_exists($group_id, $this->flattenOptions($options));
  }

  /**
   * Flattens the options grouped by group type.
   */
  protected function flattenOptions(array $options): array {
    return array_reduce($options, static fn (array $carry, array $group_options) => $carry + $group_options, []);
  }

  /**
   * Sorts the options grouped by group type by their labels.
   */
  protected function sortOptions(array $options): array {
    ksort($options, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($options as &$group_options) {
      uasort($group_options, static fn ($a, $b) => strnatcasecmp((string) $a, (string) $b));
    }
    return $options;
  }

}
