<?php

declare(strict_types=1);

namespace Drupal\Tests\service_manual_workflow\Kernel;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Form\FormState;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\service_manual_workflow\Traits\ServiceManualWorkflowTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\node\NodeInterface;
use Drupal\service_manual_workflow\Form\SetServiceOutdatedOperationForm;

/**
 * Test description.
 *
 * @group service_manual_workflow
 */
final class SetServiceOutdatedOperationTest extends GroupKernelTestBase {

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

    $this->serviceOutdatedOperationForm = new SetServiceOutdatedOperationForm(
      $this->entityTypeManager,
      \Drupal::service('service_manual_workflow.set_outdated_access'),
      \Drupal::service('service_manual_workflow.moderation_transition')

    );

  }

  /**
   * Test callback.
   */
  public function testSetServiceOutdatedOperationForm(): void {
    $user = $this->createUser([], NULL, TRUE);
    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'draft',
    ]);

    $this->setCurrentUser($user);

    $this->assertInstanceOf(AccessResultAllowed::class, $this->serviceOutdatedOperationForm->access($node));

    $node = $this->setNodeModerationState($node, 'ready_to_publish');
    $this->assertInstanceOf(AccessResultForbidden::class, $this->serviceOutdatedOperationForm->access($node));

    $node = $this->setNodeModerationState($node, 'published');
    $this->assertInstanceOf(AccessResultAllowed::class, $this->serviceOutdatedOperationForm->access($node));

    $form = [];
    $form_state = $this->getFormState($node);
    $form = $this->serviceOutdatedOperationForm->buildForm($form, $form_state, $node);
    $this->serviceOutdatedOperationForm->submitForm($form, $form_state);
    $node = $this->reloadEntity($node);
    $this->assertEquals('outdated', $node->get('moderation_state')->value);
  }

  /**
   * Emulate a form state of a submitted form.
   */
  public function getFormState(NodeInterface $node) {
    return (new FormState())->setStorage([
      'node' => $node,
    ]);
  }

}
