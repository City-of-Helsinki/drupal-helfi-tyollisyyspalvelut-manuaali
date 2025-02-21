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
    // @todo Change the autogenerated stub.
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $options = $element['#options'];

    // Get edit access permission for current field.
    $access = $this->fieldPermissionService->getFieldAccess('edit', $items, $this->currentUser, $items->getFieldDefinition());

    // If there's more than one option available don't set default value.
    if (count($options) > 1) {
      $element['#access'] = $access;
      return $element;
    }

    // If entity is new and user doesn't have access to it. Disable the field.
    if ($items->getEntity()->isNew() && $access === FALSE) {
      $element['#disabled'] = TRUE;
    }

    // If default value is not empty return element.
    if (!empty($element['#default_value'])) {
      return $element;
    }

    $default = array_key_first($options);

    // Default value for item.
    $items->set(0, $default);

    // Set default value for field.
    $element['#default_value'] = $default;

    return $element;
  }

}
