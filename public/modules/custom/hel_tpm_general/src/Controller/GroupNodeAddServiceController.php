<?php

namespace Drupal\hel_tpm_general\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\GroupMembershipLoader;
use Drupal\hel_tpm_general\Access\GroupNodeCreateAccessService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Group node add service controller.
 */
class GroupNodeAddServiceController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Content plugin id.
   *
   * @var string
   */
  protected $pluginId = 'group_node:service';

  /**
   * Constructor for GroupNodeAddServiceController.
   *
   * @param \Drupal\group\GroupMembershipLoader $groupMembershipLoader
   *   Group membership loader.
   * @param \Symfony\Component\Routing\RouterInterface $router
   *   Router service.
   * @param \Drupal\hel_tpm_general\Access\GroupNodeCreateAccessService $groupNodeCreateAccessService
   *   Group node create entity access.
   * @param \Drupal\Core\Session\AccountProxyInterface $user
   *   Current user account interface.
   */
  public function __construct(
    protected GroupMembershipLoader $groupMembershipLoader,
    protected RouterInterface $router,
    protected GroupNodeCreateAccessService $groupNodeCreateAccessService,
    protected AccountProxyInterface $user,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('group.membership_loader'),
      $container->get('router.no_access_checks'),
      $container->get('hel_tpm_general.group_node_add_access_service'),
      $container->get('current_user')
    );
  }

  /**
   * Access callback.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   Access result.
   */
  public function access() {
    return $this->groupNodeCreateAccessService->hasAccess($this->currentUser(), $this->pluginId);
  }

  /**
   * Title callback.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Translatable button label.
   */
  public function title() {
    return $this->t('Create service for group');
  }

  /**
   * Add service list method.
   *
   * @return array
   *   Array of services.
   */
  public function addServiceList() : array {
    $groups = $this->userGroups();
    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Select group'),
      '#items' => $this->generateLinks($groups),
    ];
  }

  /**
   * Group selection callback when creating a new service.
   *
   * @param array $groups
   *   Array of groups.
   *
   * @return array
   *   Array of add service groups per group.
   */
  private function generateLinks(array $groups) {
    $links = [];
    if (empty($groups)) {
      return $links;
    }
    foreach ($groups as $group) {
      if (!$this->hasCurrentUserCreateServiceAccess($group)) {
        continue;
      }
      $links[] = [
        '#type' => 'link',
        '#title' => $group->label(),
        '#url' => Url::fromUserInput($this->buildPath($group, $this->pluginId)),
      ];
    }

    return $links;
  }

  /**
   * Access check if user has access to create service.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Given group.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Access result.
   */
  public function hasCurrentUserCreateServiceAccess(GroupInterface $group) {
    return $this->groupNodeCreateAccessService->hasCreateServiceAccess($group, $this->pluginId, $this->user);
  }

  /**
   * Build path to group service creation.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group interface.
   * @param string $plugin_id
   *   Group content plugin id.
   *
   * @return string
   *   Path to group service creation.
   */
  private function buildPath(GroupInterface $group, string $plugin_id) {
    return sprintf('/group/%s/content/create/%s', $group->id(), $plugin_id);
  }

  /**
   * Get users groups.
   *
   * @return array
   *   Array of groups user belongs in.
   */
  protected function userGroups() : array {
    $groups = [];
    $memberships = $this->groupMembershipLoader->loadByUser($this->currentUser);
    if (empty($memberships)) {
      return $groups;
    }

    foreach ($memberships as $membership) {
      $groups[] = $membership->getGroup();
    }
    return $groups;
  }

}
