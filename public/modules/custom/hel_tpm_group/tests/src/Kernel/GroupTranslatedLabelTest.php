<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\Language\Language;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests querying and rendering translated group labels.
 */
#[Group('hel_tpm_group')]
class GroupTranslatedLabelTest extends GroupKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hel_tpm_mail_tools',
    'hel_tpm_group',
    'ggroup',
    'message',
    'message_notify',
    'views',
    'language',
  ];

  /**
   * Tests translations, original language fallback, and escaped output.
   */
  #[DataProvider('baseTables')]
  public function testTranslatedLabels(string $base_table): void {
    ConfigurableLanguage::createFromLangcode('fi')->save();
    ConfigurableLanguage::createFromLangcode('sv')->save();
    $type = $this->createGroupType(['creator_membership' => FALSE]);
    $group = $this->createGroup([
      'type' => $type->id(),
      'langcode' => 'en',
      'label' => 'English <title>',
    ]);
    $group->addTranslation('fi', ['label' => 'Suomenkielinen <otsikko>'])->save();
    $this->createGroup([
      'type' => $type->id(),
      'langcode' => 'fi',
      'label' => 'Alkuperäinen',
    ]);

    $langcodes = [
      'en' => ['English <title>', 'English &lt;title&gt;'],
      'fi' => ['Suomenkielinen <otsikko>', 'Suomenkielinen &lt;otsikko&gt;'],
      'sv' => ['English <title>', 'English &lt;title&gt;'],
    ];
    foreach ($langcodes as $langcode => [$label, $escaped]) {
      $this->container->get('language.default')->set(new Language(['id' => $langcode]));
      $this->container->get('language_manager')->reset();
      $view = View::create([
        'id' => 'test_group_translated_label',
        'base_table' => $base_table,
        'base_field' => 'id',
        'display' => [
          'default' => [
            'id' => 'default',
            'display_plugin' => 'default',
            'display_options' => [
              'fields' => [
                'label_translated' => [
                  'id' => 'label_translated',
                  'table' => 'groups',
                  'field' => 'label_translated',
                  'plugin_id' => 'hel_tpm_group_group_translated_label',
                ],
              ],
              'filters' => $base_table === 'groups_field_data' ? [
                'default_langcode' => [
                  'id' => 'default_langcode',
                  'table' => 'groups_field_data',
                  'field' => 'default_langcode',
                  'plugin_id' => 'boolean',
                  'value' => '1',
                ],
              ] : [],
              'query' => ['type' => 'views_query', 'options' => ['disable_sql_rewrite' => TRUE]],
              'pager' => ['type' => 'none'],
            ],
          ],
        ],
      ])->getExecutable();
      $view->execute();

      $this->assertCount(2, $view->result);
      $handler = $view->field['label_translated'];
      $this->assertContains('languages:language_interface', $handler->getCacheContexts());
      $labels = [];
      $rendered = [];
      foreach ($view->result as $row) {
        $labels[] = $handler->getValue($row);
        $rendered[] = (string) $handler->render($row);
      }
      $this->assertEqualsCanonicalizing([$label, 'Alkuperäinen'], $labels);
      $this->assertEqualsCanonicalizing([$escaped, 'Alkuperäinen'], $rendered);
      $view->destroy();
    }
  }

  /**
   * Provides group base tables supported by the field.
   */
  public static function baseTables(): array {
    return [
      'base table' => ['groups'],
      'data table' => ['groups_field_data'],
    ];
  }

}
