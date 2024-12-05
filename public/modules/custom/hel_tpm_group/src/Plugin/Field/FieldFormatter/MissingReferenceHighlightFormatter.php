<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\user\UserInterface;

/**
 * Plugin implementation of the 'Missing reference highlight' formatter.
 *
 * @FieldFormatter(
 *   id = "hel_tpm_group_missing_reference_highlight",
 *   label = @Translation("Missing reference highlight"),
 *   field_types = {"entity_reference"},
 * )
 */
final class MissingReferenceHighlightFormatter extends EntityReferenceLabelFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);
    $parent_entity = $items->getParent()->getValue();
    if (empty($elements)) {
      $elements[] = $this->addHighlightMessage([], 'user is missing');
      return $elements;
    }
    foreach ($elements as $i => $element) {
      $err = $this->validateReferencedEntity($element['#entity'], $parent_entity);
      if (empty($err)) {
        continue;
      }
      $elements[$i] = $this->addHighlightMessage($element, $err);
    }
    return $elements;
  }

  /**
   * Create highlight wrapper for element and add message.
   *
   * @param array $element
   *   Element render array.
   * @param string $message
   *   Message to render.
   *
   * @return array
   *   Renderable element array.
   */
  protected function addHighlightMessage(array $element, string $message) {
    $element['#prefix'] = '<div class="highlight-wrapper">';
    $element['message'] = [
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      '#markup' => sprintf('<div class="message">%s</div>', $this->t($message)),
    ];
    $element['#suffix'] = '</div>';
    return $element;
  }

  /**
   * Validate referenced entity.
   */
  protected function validateReferencedEntity(UserInterface $user, EntityInterface $parent_entity) {
    // If loaded user object is empty user is removed from systems.
    // Add node to result array.
    if (empty($user)) {
      return 'user missing from system';
    }
    // If user doesn't have update access add to result array.
    if (!$parent_entity->access('update', $user) || $user->isBlocked()) {
      return 'user has no update access';
    }
  }

}
