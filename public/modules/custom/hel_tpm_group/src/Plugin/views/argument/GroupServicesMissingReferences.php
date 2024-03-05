<?php

namespace Drupal\hel_tpm_group\Plugin\views\argument;

use Drupal\hel_tpm_group\ServiceMissingUpdatees;
use Drupal\node\NodeStorageInterface;
use Drupal\views\Plugin\views\argument\NumericArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Argument handler to get services missing updatees via group id.
 *
 * @ViewsArgument("group_services_missing_references")
 */
class GroupServicesMissingReferences extends NumericArgument {

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * Missing updatee service.
   *
   * @var \Drupal\hel_tpm_group\ServiceMissingUpdatees
   */
  protected $missingUpdateesService;

  /**
   * Constructs the Nid object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\node\NodeStorageInterface $node_storage
   *   The node storage handler.
   * @param \Drupal\hel_tpm_group\ServiceMissingUpdatees $missing_updatees_services
   *   Missing updatees service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, NodeStorageInterface $node_storage, ServiceMissingUpdatees $missing_updatees_services) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->nodeStorage = $node_storage;
    $this->missingUpdateesService = $missing_updatees_services;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get('hel_tpm_group.service_missing_updatees')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateArgument($arg) {
    // Validate if there is services missing updatees.
    $this->getFilterValues();
    if (empty($this->value)) {
      $arg = NULL;
      $this->argument_validated = FALSE;
    }
    return parent::validateArgument($arg);
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    $this->getFilterValues();
    $placeholder = $this->placeholder();
    $null_check = empty($this->options['not']) ? '' : " OR $this->tableAlias.$this->realField IS NULL";
    $operator = empty($this->options['not']) ? 'IN' : 'NOT IN';
    $placeholder .= '[]';
    $this->query->addWhereExpression(0, "$this->tableAlias.$this->realField $operator($placeholder)" . $null_check, [$placeholder => $this->value]);
  }

  /**
   * Get filter values for current arguments.
   */
  protected function getFilterValues() {
    $arg_value = (int) $this->argument;
    $this->value = $this->missingUpdateesService->getGroupServiceMissingUpdatee($arg_value, TRUE);
  }

}
