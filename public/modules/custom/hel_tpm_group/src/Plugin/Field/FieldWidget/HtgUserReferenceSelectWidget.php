<?php

namespace Drupal\hel_tpm_group\Plugin\Field\FieldWidget;


use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\select2\Plugin\Field\FieldWidget\Select2EntityReferenceWidget;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'hel_tpm_group_user_select_widget' widget.
 *
 * @FieldWidget(
 *   id = "hel_tpm_group_user_select_widget",
 *   label = @Translation("Hel Group User select2"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class HtgUserReferenceSelectWidget extends Select2EntityReferenceWidget {

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): Select2EntityReferenceWidget {
    $widget = parent::create($container, $configuration, $plugin_id, $plugin_definition); // TODO: Change the autogenerated stub
    $widget->currentUser = $container->get('current_user');
    return $widget;
  }

  /**
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   * @param $delta
   * @param array $element
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state); // TODO: Change the autogenerated stub

    // Set default value for field.
    if (!empty($element['#default_value'])) {
      return $element;
    }

    // Set current user as default if available.
    if (!empty($element['#options'][$this->currentUser->id()])) {
      $element['#default_value'][] = $this->currentUser->id();
    }

    return $element;
  }

  protected static function prepareFieldValues(array $values, array $element): array {
    return parent::prepareFieldValues($values, $element); // TODO: Change the autogenerated stub
  }

}