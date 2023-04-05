<?php

namespace Drupal\hel_tpm_general\Plugin\views\area;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\area\AreaPluginBase;

/**
 * Provides Add Service button field handler.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("hel_tpm_general_add_service_button")
 */
class AddServiceButton extends AreaPluginBase {
  private $groupNodeAddAccessService;

  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->groupNodeAddAccessService = \Drupal::service('hel_tpm_general.group_node_add_access_service');
  }

  public function access(AccountInterface $account) {
    $access = $this->groupNodeAddAccessService->hasAccess($account, 'group_node:service');
    if ($access instanceof AccessResultAllowed) {
      return TRUE;
    }
    return FALSE;
  }

  public function render($empty = FALSE) {
    return $this->addServiceGroupListLink();
  }

  private function addServiceGroupListLink() {
    return [
      '#type' => 'link',
      '#title' => $this->t('Add service'),
      '#url' => Url::fromRoute('hel_tpm_general.group.node.add.service'),
      '#attributes' => ['class' => ['add-service-button', 'button']]
    ];
  }
}
