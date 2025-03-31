<?php

namespace Drupal\hel_tpm_group\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\select2\Plugin\Field\FieldWidget\Select2EntityReferenceWidget;
use Drupal\select2\Select2Trait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'hel_tpm_group_user_select_widget' widget.
 *
 * @FieldWidget(
 *   id = "hel_tpm_group_select2_widget",
 *   label = @Translation("Hel Group Select2"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class GroupSelect2Widget extends Select2EntityReferenceWidget {

  use Select2Trait;

  /**
   * Field permission service.
   *
   * @var \Drupal\field_permissions\FieldPermissionsService
   */
  protected $fieldPermissionService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $widget = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $widget->fieldPermissionService = $container->get('field_permissions.permissions_service');
    return $widget;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Set edit access permission.
    $access = $this->fieldPermissionService->getFieldAccess('edit', $items, $this->currentUser, $items->getFieldDefinition());
    $element['#access'] = $access;

    // Disable the field if entity is new and user doesn't have edit access to
    // it.
    if ($items->getEntity()->isNew() && $access === FALSE) {
      $element['#disabled'] = TRUE;
    }

    // Show also current invalid values as selected field options. The content
    // can't be saved before removing the invalid value.
    foreach ($items->getValue() as $value) {
      if (!empty($value['target_id']) && !array_key_exists((int) $value['target_id'], $element['#options'])) {
        $element['#options'][(int) $value['target_id']] = $this->t("Invalid value");
        $element['#default_value'][] = $value['target_id'];
      }
    }

    // Ensure default value is not set if there are multiple options.
    if (count($element['#options']) > 1) {
      $element['#multiple'] = TRUE;
    }
    // If default value is empty, set the default value.
    elseif (empty($element['#default_value'])) {
      $default = array_key_first($element['#options']);
      // Default value for item.
      $items->set(0, $default);
      // Set default value for field.
      $element['#default_value'] = $default;
    }

    return $element;
  }

}
