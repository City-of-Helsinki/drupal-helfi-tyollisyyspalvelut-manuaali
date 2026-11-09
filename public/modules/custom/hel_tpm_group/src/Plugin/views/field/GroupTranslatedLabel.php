<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group\Plugin\views\field;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\UnchangingCacheableDependencyTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\ResultRow;
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
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['link_to_entity'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    $form['link_to_entity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link to the group'),
      '#default_value' => !empty($this->options['link_to_entity']),
    ];
    parent::buildOptionsForm($form, $form_state);
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

    if (!empty($this->options['link_to_entity'])) {
      $this->addAdditionalFields(['id']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if (!empty($this->options['link_to_entity'])) {
      $id = $this->getValue($values, 'id');
      $this->options['alter']['make_link'] = !empty($id);
      $this->options['alter']['url'] = $id ? Url::fromRoute('entity.group.canonical', ['group' => $id]) : NULL;
    }
    return parent::render($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return ['languages:' . LanguageInterface::TYPE_INTERFACE];
  }

}
