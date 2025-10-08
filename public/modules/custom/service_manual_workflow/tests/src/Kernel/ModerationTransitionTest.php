<?php

declare(strict_types=1);

namespace Drupal\Tests\service_manual_workflow\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\service_manual_workflow\Traits\ServiceManualWorkflowTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Test description.
 *
 * @group service_manual_workflow
 */
final class ModerationTransitionTest extends GroupKernelTestBase {

  /**
   * Holds the language code for translations.
   *
   * @var string
   */
  protected string $translationLangcode = 'fi';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'system',
    'user',
    'workflows',
    'hel_tpm_group',
    'field',
    'gnode',
    'service_manual_workflow',
    'content_moderation',
    'content_translation',
    'language',
    'gcontent_moderation',
    'message_notify',
    'group',
    'ggroup',
    'ggroup_role_mapper',
    'field_permissions',
    'flexible_permissions',
    'service_manual_workflow_service_test',
  ];

  use UserCreationTrait;

  use ServiceManualWorkflowTestTrait;

  /**
   * Service outdated operation form.
   *
   * @var \Drupal\service_manual_workflow\Form\SetServiceOutdatedOperationForm
   */
  protected $serviceOutdatedOperationForm;

  /**
   * Moderation transition service.
   *
   * @var \Drupal\content_moderation\Service\ModerationTransitionService
   */
  private mixed $moderationTransitionService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('group');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'node', 'system']);
    $this->installSchema('ggroup', ['group_graph']);
    $this->installConfig([
      'service_manual_workflow_service_test',
      'content_moderation',
    ]);
    $this->moderationTransitionService = \Drupal::service('service_manual_workflow.moderation_transition');
    ConfigurableLanguage::createFromLangcode($this->translationLangcode)->save();
  }

  /**
   * Tests the transition of a service node to the "outdated" state.
   *
   * @return void
   *   Return nothing.
   */
  public function testSetServiceOutdated(): void {
    $transition_state = 'outdated';
    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'draft',
    ]);
    $translation = $node->addTranslation($this->translationLangcode, ['title' => $this->randomString()]);
    $this->moderationTransitionService->setServiceOutdated($node);
    $node = $this->reloadEntity($node);
    $this->assertEquals($transition_state, $node->get('moderation_state')->value);
    $translation = $this->reloadEntity($translation);
    $this->assertEquals($transition_state, $translation->get('moderation_state')->value);

    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'published',
    ]);

    $translation = $node->addTranslation($this->translationLangcode, ['title' => $this->randomString()]);
    $this->moderationTransitionService->setServiceOutdated($node);
    $node = $this->reloadEntity($node);
    $this->assertEquals($transition_state, $node->get('moderation_state')->value);
    $translation = $this->reloadEntity($translation);
    $this->assertEquals($transition_state, $translation->get('moderation_state')->value);
  }

  /**
   * Tests the transition of a service node to the "archived" state.
   *
   * @return void
   *   Return nothing.
   */
  public function testSetServiceArchived(): void {
    $transition_state = 'archived';
    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'draft',
    ]);
    $translation = $node->addTranslation($this->translationLangcode, ['title' => $this->randomString()]);
    $this->moderationTransitionService->setServiceArchived($node);
    $node = $this->reloadEntity($node);
    $this->assertEquals($transition_state, $node->get('moderation_state')->value);
    $translation = $this->reloadEntity($translation);
    $this->assertEquals($transition_state, $translation->get('moderation_state')->value);

    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'published',
    ]);

    $translation = $node->addTranslation($this->translationLangcode, ['title' => $this->randomString()]);
    $this->moderationTransitionService->setServiceArchived($node);
    $node = $this->reloadEntity($node);
    $this->assertEquals($transition_state, $node->get('moderation_state')->value);
    $translation = $this->reloadEntity($translation);
    $this->assertEquals($transition_state, $translation->get('moderation_state')->value);
  }

}
