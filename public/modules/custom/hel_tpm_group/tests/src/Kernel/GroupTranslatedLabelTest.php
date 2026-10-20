<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Render\RenderContext;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests querying and rendering translated group labels.
 */
#[Group('hel_tpm_group')]
#[RunTestsInSeparateProcesses]
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
    $group->addTranslation('fi', ['label' => 'Finnish <title>'])->save();
    $this->createGroup([
      'type' => $type->id(),
      'langcode' => 'fi',
      'label' => 'Original',
    ]);

    $langcodes = [
      'en' => ['English <title>', 'English &lt;title&gt;'],
      'fi' => ['Finnish <title>', 'Finnish &lt;title&gt;'],
      'sv' => ['English <title>', 'English &lt;title&gt;'],
    ];
    foreach ($langcodes as $langcode => [$label, $escaped]) {
      $this->setDefaultLanguage($langcode);
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
      $this->assertEqualsCanonicalizing([$label, 'Original'], $labels);
      $this->assertEqualsCanonicalizing([$escaped, 'Original'], $rendered);
      $view->destroy();
    }
  }

  /**
   * Tests linking the translated label to the group.
   */
  public function testLinkToEntity(): void {
    ConfigurableLanguage::createFromLangcode('fi')->save();
    $type = $this->createGroupType(['creator_membership' => FALSE]);
    $group = $this->createGroup([
      'type' => $type->id(),
      'langcode' => 'en',
      'label' => 'English',
    ]);
    $group->addTranslation('fi', ['label' => 'Finnish'])->save();
    $this->setDefaultLanguage('fi');

    $view = View::create([
      'id' => 'test_group_translated_label_link',
      'base_table' => 'groups_field_data',
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
                'link_to_entity' => TRUE,
              ],
            ],
            'filters' => [
              'default_langcode' => [
                'id' => 'default_langcode',
                'table' => 'groups_field_data',
                'field' => 'default_langcode',
                'plugin_id' => 'boolean',
                'value' => '1',
              ],
            ],
            'query' => ['type' => 'views_query', 'options' => ['disable_sql_rewrite' => TRUE]],
            'pager' => ['type' => 'none'],
          ],
        ],
      ],
    ])->getExecutable();
    $view->execute();

    $this->assertCount(1, $view->result);
    $handler = $view->field['label_translated'];
    $rendered = (string) $this->container->get('renderer')->executeInRenderContext(
      new RenderContext(),
      fn () => $handler->advancedRender($view->result[0]),
    );
    $this->assertStringContainsString('href="' . $group->toUrl()->toString() . '"', $rendered);
    $this->assertStringContainsString('>Finnish</a>', $rendered);
  }

  /**
   * Sets the default language, which the interface language falls back to.
   */
  protected function setDefaultLanguage(string $langcode): void {
    $language_default = $this->container->get('language.default');
    assert($language_default instanceof LanguageDefault);
    $language_default->set(new Language(['id' => $langcode]));
    $this->container->get('language_manager')->reset();
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
