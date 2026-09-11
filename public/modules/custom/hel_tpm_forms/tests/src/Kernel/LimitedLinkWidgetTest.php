<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_forms\Kernel;

use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\link\LinkItemInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\group\Traits\NodeTypeCreationTrait;
use Drupal\user\Entity\Role;

/**
 * Tests the limited link widget.
 *
 * @group hel_tpm_forms
 */
final class LimitedLinkWidgetTest extends EntityKernelTestBase {

  use NodeTypeCreationTrait;
  use StringTranslationTrait;

  private const FIELD_NAME = 'field_limited_link';
  private const CONTENT_ADMIN_ROLE = 'content_admin';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'hel_tpm_forms',
    'link',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['filter', 'node', 'system', 'user']);

    $this->createContentTypes();
    $this->createLimitedLinkField();
    $this->createContentAdminRole();
  }

  /**
   * Tests default settings.
   */
  public function testDefaultSettings(): void {
    $settings = $this->createWidget()->getSettings();

    $this->assertSame([], $settings['target_bundles']);
    $this->assertSame([], $settings['bypass_target_bundles_roles']);
  }

  /**
   * Tests widget settings form contains bundle and role options.
   */
  public function testSettingsForm(): void {
    $widget = $this->createWidget([
      'target_bundles' => [
        'article' => 'article',
      ],
      'bypass_target_bundles_roles' => [
        self::CONTENT_ADMIN_ROLE => self::CONTENT_ADMIN_ROLE,
      ],
    ]);

    $form = $widget->settingsForm([], new FormState());

    $this->assertSame('checkboxes', $form['target_bundles']['#type']);
    $this->assertSame('Page', (string) $form['target_bundles']['#options']['page']);
    $this->assertSame('Article', (string) $form['target_bundles']['#options']['article']);
    $this->assertSame('Service', (string) $form['target_bundles']['#options']['service']);
    $this->assertSame(['article' => 'article'], $form['target_bundles']['#default_value']);

    $this->assertSame('checkboxes', $form['bypass_target_bundles_roles']['#type']);
    $this->assertSame('Content admin', (string) $form['bypass_target_bundles_roles']['#options'][self::CONTENT_ADMIN_ROLE]);
    $this->assertSame([
      self::CONTENT_ADMIN_ROLE => self::CONTENT_ADMIN_ROLE,
    ], $form['bypass_target_bundles_roles']['#default_value']);
  }

  /**
   * Tests target bundles are added to URI selection settings.
   */
  public function testFormElementLimitsTargetBundles(): void {
    $this->setCurrentUserRoles(['authenticated']);

    $element = $this->buildFormElement($this->createWidget([
      'target_bundles' => [
        'article' => 'article',
        'service' => 'service',
      ],
      'bypass_target_bundles_roles' => [
        self::CONTENT_ADMIN_ROLE => self::CONTENT_ADMIN_ROLE,
      ],
    ]));

    $this->assertSame([
      'article' => 'article',
      'service' => 'service',
    ], $element['uri']['#selection_settings']['target_bundles']);
  }

  /**
   * Tests target bundles are not added for bypass roles.
   */
  public function testFormElementDoesNotLimitTargetBundlesForBypassRole(): void {
    $this->setCurrentUserRoles(['authenticated', self::CONTENT_ADMIN_ROLE]);

    $element = $this->buildFormElement($this->createWidget([
      'target_bundles' => [
        'article' => 'article',
      ],
      'bypass_target_bundles_roles' => [
        self::CONTENT_ADMIN_ROLE => self::CONTENT_ADMIN_ROLE,
      ],
    ]));

    $this->assertArrayNotHasKey('target_bundles', $element['uri']['#selection_settings'] ?? []);
  }

  /**
   * Tests empty target bundle settings are ignored.
   */
  public function testFormElementDoesNotLimitTargetBundlesWhenSettingIsEmpty(): void {
    $this->setCurrentUserRoles(['authenticated']);

    $element = $this->buildFormElement($this->createWidget([
      'target_bundles' => [],
      'bypass_target_bundles_roles' => [],
    ]));

    $this->assertArrayNotHasKey('target_bundles', $element['uri']['#selection_settings'] ?? []);
  }

  /**
   * Creates test content types.
   */
  private function createContentTypes(): void {
    foreach ([
      'page' => 'Page',
      'article' => 'Article',
      'service' => 'Service',
    ] as $type => $name) {
      $this->createNodeType([
        'type' => $type,
        'name' => $name,
      ]);
    }
  }

  /**
   * Creates the limited link field.
   */
  private function createLimitedLinkField(): void {
    FieldStorageConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => 'node',
      'type' => 'link',
      'settings' => [
        'link_type' => LinkItemInterface::LINK_GENERIC,
        'title' => DRUPAL_OPTIONAL,
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Limited link',
    ])->save();
  }

  /**
   * Creates the content admin role.
   */
  private function createContentAdminRole(): void {
    Role::create([
      'id' => self::CONTENT_ADMIN_ROLE,
      'label' => 'Content admin',
    ])->save();
  }

  /**
   * Creates the limited link widget plugin.
   */
  private function createWidget(array $settings = []): WidgetInterface {
    $field_definition = FieldConfig::loadByName('node', 'page', self::FIELD_NAME);
    $widget_manager = $this->container->get('plugin.manager.field.widget');

    return $widget_manager->createInstance('limited_link_widget', [
      'field_definition' => $field_definition,
      'form_mode' => 'default',
      'prepare' => TRUE,
      'settings' => $settings,
      'third_party_settings' => [],
    ]);
  }

  /**
   * Builds the widget form element.
   */
  private function buildFormElement(WidgetInterface $widget): array {
    $node = Node::create([
      'type' => 'page',
      'title' => $this->t('Test page'),
    ]);
    $node->get(self::FIELD_NAME)->appendItem();

    $element = [
      '#title' => $this->t('Limited link'),
      '#description' => '',
      '#field_parents' => [],
      '#required' => FALSE,
      '#delta' => 0,
      '#weight' => 0,
    ];
    $form = [];

    return $widget->formElement(
      $node->get(self::FIELD_NAME),
      0,
      $element,
      $form,
      new FormState(),
    );
  }

  /**
   * Sets roles for the current user service.
   *
   * @param string[] $roles
   *   Role IDs.
   */
  private function setCurrentUserRoles(array $roles): void {
    $account = $this->createUser();

    foreach (array_diff($roles, ['authenticated']) as $role) {
      $account->addRole($role);
    }

    $account->save();
    $this->container->get('current_user')->setAccount($account);
  }

}
