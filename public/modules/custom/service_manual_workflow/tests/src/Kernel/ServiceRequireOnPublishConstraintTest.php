<?php

declare(strict_types=1);

namespace Drupal\Tests\service_manual_workflow\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\service_manual_workflow\Plugin\Validation\Constraint\ServiceRequireOnPublishConstraint;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\service_manual_workflow\Traits\ServiceManualWorkflowTestTrait;

/**
 * Tests the service require-on-publish constraint validator.
 *
 * @group service_manual_workflow
 */
final class ServiceRequireOnPublishConstraintTest extends GroupKernelTestBase {

  use ServiceManualWorkflowTestTrait;

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
    'hel_tpm_mail_tools',
    'require_on_publish',
    'service_manual_workflow_service_test',
  ];
  /**
   * The tested constraint.
   *
   * @var \Drupal\service_manual_workflow\Plugin\Validation\Constraint\ServiceRequireOnPublishConstraint
   */
  private ServiceRequireOnPublishConstraint $constraint;

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

    $this->constraint = new ServiceRequireOnPublishConstraint();
  }

  /**
   * Tests ready-to-publish state is treated as published.
   */
  public function testReadyToPublishRequiresConfiguredFields(): void {
    $this->setRequireOnPublish('field_service_producer');

    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'ready_to_publish',
      'field_service_producer' => [],
    ]);

    $violations = $this->container
      ->get('typed_data_manager')
      ->getValidator()
      ->validate($node->getTypedData(), $this->constraint);

    $this->assertCount(1, $violations);
    $this->assertSame('field_service_producer', $violations->get(0)->getPropertyPath());
  }

  /**
   * Tests exempt service fields are not required in ready-to-publish state.
   */
  public function testReadyToPublishDoesNotRequireExemptServiceFields(): void {
    $this->setRequireOnPublish('field_responsible_updatee');

    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'ready_to_publish',
      'field_responsible_updatee' => [],
    ]);

    $violations = $this->container
      ->get('typed_data_manager')
      ->getValidator()
      ->validate($node->getTypedData(), $this->constraint);

    $this->assertCount(0, $violations);
  }

  /**
   * Tests exempt service fields are still required in published state.
   */
  public function testPublishedRequiresExemptServiceFields(): void {
    $this->setRequireOnPublish('field_responsible_updatee');

    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'published',
      'field_responsible_updatee' => [],
    ]);

    $violations = $this->container
      ->get('typed_data_manager')
      ->getValidator()
      ->validate($node->getTypedData(), $this->constraint);

    $this->assertCount(1, $violations);
    $this->assertSame('field_responsible_updatee', $violations->get(0)->getPropertyPath());
  }

  /**
   * Tests draft state does not require require-on-publish fields.
   */
  public function testDraftDoesNotRequireConfiguredFields(): void {
    $this->setRequireOnPublish('field_service_producer');

    $node = $this->createNode([
      'type' => 'service',
      'moderation_state' => 'draft',
      'field_service_producer' => [],
    ]);

    $violations = $this->container
      ->get('typed_data_manager')
      ->getValidator()
      ->validate($node->getTypedData(), $this->constraint);

    $this->assertCount(0, $violations);
  }

  /**
   * Enables require-on-publish for a service field.
   *
   * @param string $field_name
   *   The field name.
   */
  private function setRequireOnPublish(string $field_name): void {
    $field_config = FieldConfig::load("node.service.$field_name");
    $this->assertInstanceOf(FieldConfig::class, $field_config);

    $field_config
      ->setThirdPartySetting('require_on_publish', 'require_on_publish', TRUE)
      ->save();
  }

}
