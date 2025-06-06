<?php

namespace Drupal\hel_tpm_general\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'RealUsername' formatter.
 *
 * @FieldFormatter(
 *   id = "hel_tpm_general_real_username",
 *   label = @Translation("Real Username"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class RealUsernameFormatter extends FormatterBase {

  /**
   * Group membership laoder.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected $groupMembershipLoader;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructor for RealUsernameFormatter.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\group\GroupMembershipLoaderInterface $group_membership_loader
   *   Group membership loader.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current user account interface.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    GroupMembershipLoaderInterface $group_membership_loader,
    AccountProxyInterface $current_user,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->groupMembershipLoader = $group_membership_loader;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('group.membership_loader'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#markup' => $this->generateRealUsername($item),
      ];
    }

    return $element;
  }

  /**
   * Get groups for given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   User account interface.
   *
   * @return \Drupal\group\GroupMembership[]
   *   Users group memberships.
   */
  protected function getUserGroups(AccountInterface $user) : array {
    $memberships = &drupal_static(__FUNCTION__ . '-' . $user->id());
    if (!empty($memberships)) {
      return $memberships;
    }

    $memberships = [];
    $group_memberships = $this->groupMembershipLoader->loadByUser($user);

    if (empty($group_memberships)) {
      return [];
    }
    foreach ($group_memberships as $group_membership) {
      $memberships[$group_membership->getGroup()->id()] = $group_membership;
    }

    return $memberships;
  }

  /**
   * Generate real username.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   Field item.
   *
   * @return string
   *   Formatted username.
   */
  private function generateRealUsername(FieldItemInterface $item) : string {
    $user = $item->getEntity();

    if (empty($user) || $user->isAnonymous()) {
      return 'Anonymous';
    }

    if (!$this->showRealUsernameAccess($user)) {
      return $item->value;
    }

    if ($user->get('field_name')->isEmpty()) {
      return $user->get('mail')->value;
    }

    return sprintf('%s (%s)', $user->get('field_name')->value, $user->get('mail')->value);
  }

  /**
   * Checks if user has access to see real username.
   *
   * @param \Drupal\user\UserInterface $user
   *   User interface.
   *
   * @return bool
   *   Access permission.
   */
  private function showRealUsernameAccess(UserInterface $user) : bool {
    if ($this->currentUser->hasPermission('administer users')) {
      return TRUE;
    }

    $c_user_groups = $this->getUserGroups($this->currentUser);
    $user_groups = $this->getUserGroups($user);
    if (empty($c_user_groups) || empty($user_groups)) {
      return FALSE;
    }

    $shared_groups = array_intersect_key($c_user_groups, $user_groups);
    foreach ($shared_groups as $membership) {
      if ($membership->hasPermission('administer members')) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
