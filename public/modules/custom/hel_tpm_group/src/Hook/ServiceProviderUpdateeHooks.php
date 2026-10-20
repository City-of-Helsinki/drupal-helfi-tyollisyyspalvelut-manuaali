<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\node\NodeInterface;

/**
 * Refreshes the service provider updatee options when service groups change.
 *
 * The updatee options are the editors of the service groups. Without this the
 * options would follow the groups only after the service has been saved.
 */
class ServiceProviderUpdateeHooks {

  use StringTranslationTrait;

  /**
   * The service provider updatee field.
   */
  public const UPDATEE_FIELD = 'field_service_provider_updatee';

  /**
   * The computed field holding the group relationships of the service.
   */
  public const GROUP_FIELD = 'entitygroupfield';

  /**
   * HTML ID of the updatee field wrapper.
   */
  protected const WRAPPER_ID = 'hel-tpm-group-service-provider-updatee';

  /**
   * Group widget buttons that change the groups of the service.
   */
  protected const ITEM_BUTTONS = [
    'remove_button',
    'confirm_remove_button',
    'restore_button',
  ];

  public function __construct(
    protected readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node_form.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface) {
      return;
    }

    $node = $form_object->getEntity();
    if (!$node instanceof NodeInterface
      || $node->bundle() !== 'service'
      || empty($form[self::GROUP_FIELD]['widget'])
      || !isset($form[self::UPDATEE_FIELD]['widget']['#options'])) {
      return;
    }

    $widget = &$form[self::UPDATEE_FIELD]['widget'];
    $options = $this->getOptions($node, $this->getFormRelationships($form, $form_state));
    $empty_label = $widget['#options']['_none'] ?? NULL;
    $selected = array_filter((array) ($widget['#default_value'] ?? []));
    // Require a new selection when the selected user isn't an option anymore.
    if ($empty_label === NULL && array_diff($selected, array_keys($options))) {
      $empty_label = $this->t('- Select a value -');
    }
    $widget['#options'] = $empty_label === NULL ? $options : ['_none' => $empty_label] + $options;

    $form[self::UPDATEE_FIELD]['#prefix'] = '<div id="' . self::WRAPPER_ID . '">';
    $form[self::UPDATEE_FIELD]['#suffix'] = '</div>';
    $form[self::UPDATEE_FIELD]['#hel_tpm_group_updatee'] = TRUE;

    $this->attachAjax($form[self::GROUP_FIELD]['widget']);
  }

  /**
   * Gets the updatee options for the service with the given groups.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The service.
   * @param \Drupal\group\Entity\GroupRelationshipInterface[] $relationships
   *   The group relationships of the service, saved or not.
   *
   * @return array
   *   Option labels keyed by user ID.
   */
  public function getOptions(NodeInterface $node, array $relationships): array {
    // Use a copy so the form entity keeps its own group values.
    $service = clone $node;
    $service->set(self::GROUP_FIELD, array_map(
      static fn (GroupRelationshipInterface $relationship): array => ['entity' => $relationship],
      $relationships,
    ));

    $options = $service->getFieldDefinition(self::UPDATEE_FIELD)
      ->getFieldStorageDefinition()
      ->getOptionsProvider('target_id', $service)
      ->getSettableOptions($this->currentUser);

    // Sanitize the labels the same way as the options widgets do.
    array_walk_recursive($options, static function (&$label): void {
      $label = Html::decodeEntities(strip_tags((string) $label));
    });
    return $options;
  }

  /**
   * Ajax callback for the group widget buttons.
   *
   * Runs the group widget's own callback and also replaces the updatee field,
   * whose options were rebuilt for the changed groups.
   */
  public static function groupWidgetAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $button = $form_state->getTriggeringElement();
    $callback = $form_state->prepareCallback($button['#hel_tpm_group_ajax_callback']);
    $group_widget = call_user_func_array($callback, [&$form, $form_state]);

    $response = new AjaxResponse();
    // Without a selector the wrapper of the triggering button is replaced.
    $response->addCommand(new ReplaceCommand(NULL, $group_widget));

    $updatee = static::findUpdatee($form);
    if ($updatee !== NULL && ($updatee['#access'] ?? TRUE)) {
      // Field groups nest fields with #group, and an element with #group is
      // only rendered inside its group. Render the field on its own instead.
      unset($updatee['#group']);
      $response->addCommand(new ReplaceCommand('#' . self::WRAPPER_ID, $updatee));
    }
    return $response;
  }

  /**
   * Gets the group relationships currently selected in the group widget.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\group\Entity\GroupRelationshipInterface[]
   *   Saved and unsaved relationships, excluding removed ones.
   */
  protected function getFormRelationships(array $form, FormStateInterface $form_state): array {
    $widget_state = WidgetBase::getWidgetState($form['#parents'] ?? [], self::GROUP_FIELD, $form_state);

    $relationships = [];
    foreach ($widget_state['gcontent'] ?? [] as $item) {
      // Items marked for removal aren't saved either.
      if (in_array($item['mode'] ?? '', ['remove', 'removed'], TRUE)) {
        continue;
      }
      if (($item['entity'] ?? NULL) instanceof GroupRelationshipInterface) {
        $relationships[] = $item['entity'];
      }
    }
    return $relationships;
  }

  /**
   * Makes the group widget buttons refresh the updatee field too.
   *
   * @param array $widget
   *   The group widget elements.
   */
  protected function attachAjax(array &$widget): void {
    if (isset($widget['add_more']['add_more_button'])) {
      $this->overrideAjax($widget['add_more']['add_more_button']);
    }
    foreach (Element::children($widget) as $delta) {
      foreach (self::ITEM_BUTTONS as $button) {
        if (isset($widget[$delta]['top']['links'][$button])) {
          $this->overrideAjax($widget[$delta]['top']['links'][$button]);
        }
      }
    }
  }

  /**
   * Replaces the Ajax callback of a button, keeping the original one.
   *
   * @param array $button
   *   The button element.
   */
  protected function overrideAjax(array &$button): void {
    if (empty($button['#ajax']['callback'])) {
      return;
    }
    $button['#hel_tpm_group_ajax_callback'] = $button['#ajax']['callback'];
    $button['#ajax']['callback'] = [static::class, 'groupWidgetAjax'];
  }

  /**
   * Finds the updatee field element, which field groups may have moved.
   *
   * @param array $element
   *   The form or a form element.
   *
   * @return array|null
   *   The updatee field element, or NULL if not found.
   */
  protected static function findUpdatee(array $element): ?array {
    if (!empty($element['#hel_tpm_group_updatee'])) {
      return $element;
    }
    foreach (Element::children($element) as $key) {
      if (is_array($element[$key]) && ($found = static::findUpdatee($element[$key])) !== NULL) {
        return $found;
      }
    }
    return NULL;
  }

}
