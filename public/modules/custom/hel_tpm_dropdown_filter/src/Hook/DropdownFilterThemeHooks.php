<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_dropdown_filter\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;

/**
 * Theme hooks for the dropdown filter widget.
 */
class DropdownFilterThemeHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'hel_tpm_dropdown_filter' => [
        'render element' => 'element',
        'initial preprocess' => static::class . ':preprocessDropdownFilter',
      ],
    ];
  }

  /**
   * Prepares variables for the dropdown filter template.
   *
   * Default template: hel-tpm-dropdown-filter.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - element: A checkboxes form element with #dropdown_groups and
   *     #dropdown_single properties.
   */
  public function preprocessDropdownFilter(array &$variables): void {
    $element = $variables['element'];
    $name = $element['#name'] ?? $element['#parents'][0];

    // Use the value of the whole element, as the child checkboxes don't get
    // their state from URLs like ?field[0]=value.
    $value = is_array($element['#value'] ?? NULL) ? $element['#value'] : [];

    $items = [];
    foreach (Element::children($element) as $key) {
      $checkbox = $element[$key];
      $items[$key] = [
        'id' => $checkbox['#id'],
        'name' => $checkbox['#name'],
        'value' => $checkbox['#return_value'],
        'label' => $checkbox['#title'],
        'checked' => isset($value[$checkbox['#return_value']]),
        'disabled' => !empty($checkbox['#disabled']),
      ];
    }

    $groups = [];
    foreach ($element['#dropdown_groups'] ?? [] as $label => $keys) {
      $groups[] = [
        'label' => $label,
        'items' => array_intersect_key($items, array_flip($keys)),
      ];
    }

    $selected = array_column(array_filter($items, fn ($item) => $item['checked']), 'label');

    $variables['attributes'] = new Attribute([
      'class' => [
        'form-item',
        'js-form-item',
        'form-item-select',
        'js-form-type-checkboxes',
        'form-item-' . Html::getClass($name),
        'js-form-item-' . Html::getClass($name),
      ],
      'data-dropdown-filter' => $name,
    ]);
    if (!empty($element['#dropdown_single'])) {
      $variables['attributes']['data-dropdown-filter-single'] = '';
    }
    $variables['id'] = $element['#id'];
    $variables['title'] = $element['#title'] ?? '';
    $variables['placeholder'] = !empty($element['#description']) ? $element['#description'] : $this->t('- Any -');
    $variables['selected'] = $selected;
    $variables['groups'] = $groups;
    $variables['items'] = $groups ? [] : $items;
    $variables['dropdown_modifiers'] = '';
  }

}
