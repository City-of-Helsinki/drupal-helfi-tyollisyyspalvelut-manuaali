<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_forms\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Hooks for the service location accessibility display.
 */
final class LocationAccessibilityHooks {

  use StringTranslationTrait;

  /**
   * Pseudo field shown when the location has no accessibility information.
   */
  public const MISSING_NOTICE = 'location_accessibility_missing';

  /**
   * Declares the missing accessibility information pseudo field.
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    $extra['node']['service_location']['display'][self::MISSING_NOTICE] = [
      'label' => $this->t('Missing accessibility information notice'),
      'description' => $this->t('Shown when the location has no accessibility information.'),
      'weight' => 30,
      'visible' => FALSE,
    ];
    return $extra;
  }

  /**
   * Adds the notice when the location has no accessibility information.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($node->bundle() !== 'service_location'
      || !$display->getComponent(self::MISSING_NOTICE)
      || $this->hasAccessibilityInformation($node)) {
      return;
    }

    $build[self::MISSING_NOTICE] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => [
        'class' => ['location-accessibility__missing'],
      ],
      'text' => [
        '#type' => 'html_tag',
        '#tag' => 'strong',
        '#value' => $this->t('No accessibility information has been added for this location.'),
      ],
    ];
  }

  /**
   * Checks whether the location has any accessibility information to show.
   */
  private function hasAccessibilityInformation(NodeInterface $node): bool {
    if ($node->hasField('field_accessibility')
      && $node->get('field_accessibility')->referencedEntities()) {
      return TRUE;
    }
    return $node->hasField('field_accessibility_info')
      && !$node->get('field_accessibility_info')->isEmpty();
  }

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
