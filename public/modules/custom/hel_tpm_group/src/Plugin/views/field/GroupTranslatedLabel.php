<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group\Plugin\views\field;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\UnchangingCacheableDependencyTrait;
use Drupal\Core\Language\LanguageInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\ViewsHandlerManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Queries group labels in the current interface language.
 */
#[ViewsField('hel_tpm_group_group_translated_label')]
final class GroupTranslatedLabel extends FieldPluginBase implements CacheableDependencyInterface {

  use UnchangingCacheableDependencyTrait;

  /**
   * Views join plugin manager.
   *
   * @var \Drupal\views\Plugin\ViewsHandlerManager
   */
  protected ViewsHandlerManager $joinManager;

  /**
   * Constructs the translated label field handler.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    #[Autowire(service: 'plugin.manager.views.join')]
    ViewsHandlerManager $join_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->joinManager = $join_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();

    // Join each translation separately to avoid adding rows for every language.
    // Use the original label when the requested translation does not exist.
    $tables = [];
    foreach ([
      'current' => ['langcode', '***LANGUAGE_' . LanguageInterface::TYPE_INTERFACE . '***'],
      'original' => ['default_langcode', 1],
    ] as $translation => [$field, $value]) {
      $join = $this->joinManager->createInstance('standard', [
        'table' => 'groups_field_data',
        'field' => 'id',
        'left_table' => $this->tableAlias,
        'left_field' => 'id',
        'type' => 'LEFT',
        'extra' => [
          ['field' => $field, 'value' => $value],
        ],
        'adjusted' => TRUE,
      ]);
      $tables[$translation] = $this->query->addTable(
        'groups_field_data',
        $this->relationship,
        $join,
        $this->tableAlias . '_label_' . $translation,
      );
    }

    $params = $this->options['group_type'] != 'group' ? ['function' => $this->options['group_type']] : [];
    $this->field_alias = $this->query->addField(
      NULL,
      "COALESCE({$tables['current']}.label, {$tables['original']}.label)",
      $this->tableAlias . '_label_translated',
      $params,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return ['languages:' . LanguageInterface::TYPE_INTERFACE];
  }

}
