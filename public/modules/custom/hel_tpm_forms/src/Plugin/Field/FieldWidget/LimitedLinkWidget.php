<?php

namespace Drupal\hel_tpm_forms\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'link' widget.
 */
#[FieldWidget(
  id: 'limited_link_widget',
  label: new TranslatableMarkup('Limited Link'),
  field_types: ['link'],
)]
class LimitedLinkWidget extends LinkWidget {

  /**
   * Manages interactions with entity types and their storage handlers.
   *
   * Provides methods to retrieve and interact with entity definitions,
   * storage handlers, and other entity-related functionality.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Stores the current authenticated user instance.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );

  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings() + [
      'target_bundles' => [],
      'bypass_target_bundles_roles' => [],
    ];
  }

  /**
   * Builds the settings form for the configuration of the plugin.
   *
   * This method adds configuration options to the form, allowing users to
   * specify target bundles and roles that can bypass bundle restrictions.
   *
   * @param array $form
   *   A keyed array containing the basic structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form as given by the form API.
   *
   * @return array
   *   A modified form array that includes settings for target bundles and
   *   bypass roles.
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['target_bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Target bundles'),
      '#options' => $this->getTargetBundleOptions(),
      '#default_value' => $this->getSetting('target_bundles'),
    ];
    $form['bypass_target_bundles_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Bypass target bundles roles'),
      '#options' => $this->getUserRoles(),
      '#default_value' => $this->getSetting('bypass_target_bundles_roles'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    if ($this->byPassTargetBundle()) {
      return $element;
    }
    if ($target_bundles = $this->getSetting('target_bundles')) {
      $element['uri']['#selection_settings']['target_bundles'] = $target_bundles;
    }
    return $element;
  }

  /**
   * Determines if the current user can bypass target bundle restrictions.
   *
   * This method checks the roles of the current user against a configured
   * set of roles allowed to bypass restrictions on target bundles.
   *
   * @return bool
   *   TRUE if the current user has any role that permits bypassing target
   *   bundle restrictions, FALSE otherwise.
   */
  protected function byPassTargetBundle(): bool {
    $user_roles = $this->currentUser->getRoles();
    $bypass_roles = $this->getSetting('bypass_target_bundles_roles');
    return !empty(array_intersect($user_roles, $bypass_roles));
  }

  /**
   * Retrieves a list of user roles.
   *
   * This method loads all available user roles and populates an associative
   * array with their IDs and human-readable labels.
   *
   * @return array
   *   An associative array where the keys are the role IDs, and the values
   *   are the corresponding human-readable labels for those roles.
   */
  private function getUserRoles(): array {
    $roles = [];
    foreach ($this->entityTypeManager->getStorage('user_role')->loadMultiple() as $role_id => $role) {
      $roles[$role_id] = $role->label();
    }
    return $roles;
  }

  /**
   * Retrieves a list of target bundle options for node types.
   *
   * This method loads all available content type bundles and gathers
   * their machine names and human-readable labels into an associative array.
   *
   * @return array
   *   An associative array where keys are the machine names of the content
   *   types, and values are their corresponding human-readable labels.
   */
  private function getTargetBundleOptions(): array {
    $target_bundles = [];
    $bundles = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    foreach ($bundles as $bundle => $bundle_info) {
      $target_bundles[$bundle] = $bundle_info->label();
    }
    return $target_bundles;
  }

}
