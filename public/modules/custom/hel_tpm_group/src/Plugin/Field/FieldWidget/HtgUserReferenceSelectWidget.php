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
 *   id = "hel_tpm_group_user_select_widget",
 *   label = @Translation("Hel Group User select2"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class HtgUserReferenceSelectWidget extends Select2EntityReferenceWidget {

  use Select2Trait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): Select2EntityReferenceWidget {
    $widget = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $widget->setEntityTypeManager($container->get('entity_type.manager'));
    return $widget;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Set default value for field.
    if ($items->count() > 0) {
      $element['#access'] = $this->hasFieldAccess($items, $element);
      return $element;
    }

    // Set current user as default if available.
    if (!empty($element['#options'][$this->currentUser->id()])) {
      $element['#default_value'][] = $this->currentUser->id();
    }

    return $element;
  }

  /**
   * Check if user can be give access to edit reference.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field item list containing field items.
   * @param array $element
   *   Element render array.
   *
   * @return bool
   *   Retrun TRUE if user can access items.
   */
  protected function hasFieldAccess(FieldItemListInterface $items, $element) {
    $values = $items->getValue();
    $options = $element['#options'];

    foreach ($values as $value) {
      if (empty($options[$value['target_id']])) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
