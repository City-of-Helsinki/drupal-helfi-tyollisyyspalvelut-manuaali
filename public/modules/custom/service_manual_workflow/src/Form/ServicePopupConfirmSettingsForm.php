<?php

namespace Drupal\service_manual_workflow\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Service manual workflow settings for this site.
 */
class ServicePopupConfirmSettingsForm extends ConfigFormBase {

  /**
   * Workflow id confirm form is used in.
   *
   * @var string
   */
  private $workflowId = 'service_moderation';

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructor for service popup confirm settings form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   Entity type bundle info.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'service_manual_workflow_service_popup_confirm_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['service_manual_workflow.popup_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config($this->getEditableConfigNames()[0]);
    $workflow_config = $this->getWorkflowConfiguration();
    foreach ($workflow_config['states'] as $state_id => $state) {
      $form[$state_id] = [
        '#type' => 'textarea',
        '#title' => $this->t($state['label']),
        '#default_value' => $config->get($state_id),
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * Getter for selected workflow configuration.
   *
   * @return mixed
   *   Workflow configuration.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getWorkflowConfiguration() {
    $workflow = $this->entityTypeManager->getStorage('workflow')->load($this->workflowId);
    return $workflow->getPluginCollections()['type_settings']->getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->getWorkflowConfiguration();
    foreach ($config['states'] as $key => $state) {
      $val = trim($form_state->getValue($key));
      if (empty($val)) {
        continue;
      }
      $this->config($this->getEditableConfigNames()[0])
        ->set($key, $val)
        ->save();
    }
    parent::submitForm($form, $form_state);
  }

}
