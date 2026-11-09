<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_forms\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Hooks for the service location accessibility display.
 */
final class LocationAccessibilityHooks {

  use StringTranslationTrait;

  /**
   * Adds the description under the location accessibility group label.
   *
   * Field groups are nested in a preprocess function that runs after the
   * theme's preprocess functions, so this alter hook is the first point where
   * group_location_accessibility exists in the build.
   */
  #[Hook('field_group_build_pre_render_alter')]
  public function fieldGroupBuildPreRenderAlter(array &$element): void {
    if (($element['#view_mode'] ?? NULL) !== 'location_accessibility'
      || !(($element['#node'] ?? NULL) instanceof NodeInterface)
      || $element['#node']->bundle() !== 'service_location'
      || !isset($element['group_location_accessibility'])) {
      return;
    }

    $group = &$element['group_location_accessibility'];
    $infobox_weight = $group['group_infobox']['#weight'] ?? 20;

    $group['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('This information is linked to the specific location and cannot be edited. Please contact the Service Manual’s administrators at <a href=":mailto">@email</a> if the accessibility information is incomplete or incorrect.', [
        ':mailto' => 'mailto:palvelumanuaali@hel.fi',
        '@email' => 'palvelumanuaali@hel.fi',
      ]),
      '#attributes' => [
        'class' => ['location-accessibility__description'],
      ],
      '#weight' => $infobox_weight - 1,
    ];
  }

}
