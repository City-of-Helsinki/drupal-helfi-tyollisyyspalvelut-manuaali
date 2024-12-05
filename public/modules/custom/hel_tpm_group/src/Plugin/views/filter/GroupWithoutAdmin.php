<?php

namespace Drupal\hel_tpm_group\Plugin\views\filter;

use Drupal\hel_tpm_group\GroupsWithoutAdmins;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter groups without admin users.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("group_without_admin_filter")
 */
class GroupWithoutAdmin extends InOperator {

  /**
   * Groups without admins service.
   *
   * @var \Drupal\hel_tpm_group\GroupsWithoutAdmins
   */
  protected $groupsWithoutAdminsService;

  /**
   * Constuctor for group without admin filter.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\hel_tpm_group\GroupsWithoutAdmins $groupsWithoutAdminsService
   *   Groups without admins service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GroupsWithoutAdmins $groupsWithoutAdminsService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->groupsWithoutAdminsService = $groupsWithoutAdminsService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('hel_tpm_group.groups_without_admins')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);
    $this->valueTitle = $this->t('Groups without admin');
    $this->definition['options callback'] = [$this, 'generateOptions'];
  }

  /**
   * Generate place holder options.
   *
   * @return string[]
   *   Filter options.
   */
  protected function generateOptions(): array {
    return ['all' => $this->t('Show all')];
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple() {
    $this->value = \Drupal::service('hel_tpm_group.groups_without_admins')->groupsWithoutAdmins();
    parent::opSimple();
  }

}
