<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\hel_tpm_group\Traits\GroupInitTrait;
use Drupal\Tests\service_manual_workflow\Traits\ServiceManualWorkflowTestTrait;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\hel_tpm_group\Plugin\Field\FieldWidget\EntityGroupFieldSelect2Widget;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the group Select2 widget of the entity group field.
 */
#[CoversClass(EntityGroupFieldSelect2Widget::class)]
#[Group('hel_tpm_group')]
#[RunTestsInSeparateProcesses]
class EntityGroupFieldSelect2WidgetTest extends GroupKernelTestBase {

  use ContentModerationTestTrait;
  use GroupInitTrait;
  use ServiceManualWorkflowTestTrait;

  /**
   * The group field.
   */
  protected const FIELD = 'entitygroupfield';

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'node',
    'system',
    'user',
    'workflows',
    'hel_tpm_mail_tools',
    'hel_tpm_group',
    'field',
    'content_moderation',
    'content_translation',
    'language',
    'gcontent_moderation',
    'message',
    'message_notify',
    'message_notify_test',
    'gnode',
    'group',
    'ggroup',
    'ggroup_role_mapper',
    'field_permissions',
    'flexible_permissions',
    'entitygroupfield',
    'service_manual_workflow',
    'service_manual_workflow_service_test',
  ];

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
    $this->installSchema('ggroup', ['group_graph']);

    $this->installConfig(['system', 'field', 'node']);
    $this->installConfig([
      'content_moderation',
      'gcontent_moderation',
      'group',
      'workflows',
      'hel_tpm_group',
      'service_manual_workflow_service_test',
    ]);

    $this->initGroups(TRUE);
  }

  /**
   * Tests that keeping the selected group keeps its relationship.
   */
  public function testUnchangedGroupKeepsRelationship(): void {
    $this->setCurrentUser($this->createUser([EntityGroupFieldSelect2Widget::PERMISSION]));
    $node = $this->createService([$this->orgGroup]);
    $relationship_ids = array_keys($this->loadRelationships($node));

    $element = $this->buildWidget($node, $form_state);
    $this->assertEmpty($element['#disabled'] ?? FALSE);
    $this->assertFalse($element['#multiple']);
    $this->assertTrue($element['#required']);
    $this->assertEquals($this->orgGroup->id(), $element['#default_value']);

    $this->submitWidget($node, $form_state, $this->orgGroup);
    $this->assertSame($relationship_ids, array_keys($this->loadRelationships($node)));
  }

  /**
   * Tests that selecting another group replaces the relationship.
   */
  public function testChangedGroupReplacesRelationship(): void {
    $this->setCurrentUser($this->createUser([EntityGroupFieldSelect2Widget::PERMISSION]));
    $node = $this->createService([$this->orgGroup]);
    $old_ids = array_keys($this->loadRelationships($node));

    $this->buildWidget($node, $form_state);
    $this->submitWidget($node, $form_state, $this->spGroup);

    $relationships = $this->loadRelationships($node);
    $this->assertSame([(int) $this->spGroup->id()], $this->getGroupIds($relationships));
    $this->assertEmpty(array_intersect($old_ids, array_keys($relationships)));
  }

  /**
   * Tests a service that belongs to multiple groups.
   */
  public function testMultipleGroupsRequireSelection(): void {
    $this->setCurrentUser($this->createUser([EntityGroupFieldSelect2Widget::PERMISSION]));
    $node = $this->createService([$this->orgGroup, $this->spGroup]);
    $sp_relationship_ids = array_keys(array_filter(
      $this->loadRelationships($node),
      fn (GroupRelationshipInterface $relationship): bool => $relationship->getGroupId() == $this->spGroup->id(),
    ));

    $element = $this->buildWidget($node, $form_state);
    // No group is preselected, so one has to be selected.
    $this->assertNull($element['#default_value']);
    $this->assertFalse($element['#multiple']);
    $this->assertArrayHasKey('multiple_groups', $element['#description']);

    $this->submitWidget($node, $form_state, $this->spGroup);
    $this->assertSame($sp_relationship_ids, array_keys($this->loadRelationships($node)));
  }

  /**
   * Tests that the groups can't be changed without the permission.
   */
  public function testReadOnlyWithoutPermission(): void {
    $this->setCurrentUser($this->orgUser);
    $node = $this->createService([$this->orgGroup, $this->spGroup]);
    $relationship_ids = array_keys($this->loadRelationships($node));

    $element = $this->buildWidget($node, $form_state);
    $this->assertTrue($element['#disabled']);
    $this->assertFalse($element['#required']);
    $this->assertTrue($element['#multiple']);
    $this->assertEqualsCanonicalizing([$this->orgGroup->id(), $this->spGroup->id()], $element['#default_value']);

    $this->submitWidget($node, $form_state, $this->spGroup);
    $this->assertSame($relationship_ids, array_keys($this->loadRelationships($node)));
  }

  /**
   * Tests the widget when creating a service in a group.
   */
  public function testGroupCreationContext(): void {
    $this->setCurrentUser($this->createUser([EntityGroupFieldSelect2Widget::PERMISSION]));
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'service',
      'title' => $this->randomMachineName(),
      'uid' => $this->orgUser->id(),
    ]);
    $form_state = new FormState();
    $form_state->set('group_wizard_id', 'group_entity');
    $form_state->set('group', $this->orgGroup);

    $element = $this->buildWidget($node, $form_state);
    // The group module adds the service to the group of the route.
    $this->assertTrue($element['#disabled']);
    $this->assertEquals($this->orgGroup->id(), $element['#default_value']);
    $this->assertSame([], EntityGroupFieldSelect2Widget::getSelectedRelationships(
      EntityGroupFieldSelect2Widget::getWidgetState([], self::FIELD, $form_state),
    ));
  }

  /**
   * Builds the widget element.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The service.
   * @param \Drupal\Core\Form\FormState|null $form_state
   *   The form state, created if not given.
   *
   * @return array
   *   The widget element.
   */
  protected function buildWidget(NodeInterface $node, ?FormState &$form_state): array {
    $form_state ??= new FormState();
    $form = ['#parents' => []];
    $field = $this->getWidget()->form($node->get(self::FIELD), $form, $form_state);
    return $field['widget'];
  }

  /**
   * Submits a group selection and saves the service.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The service.
   * @param \Drupal\Core\Form\FormState $form_state
   *   The form state of the built widget.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The selected group.
   */
  protected function submitWidget(NodeInterface $node, FormState $form_state, GroupInterface $group): void {
    $form_state->setValues([self::FIELD => (string) $group->id()]);
    $this->getWidget()->extractFormValues($node->get(self::FIELD), ['#parents' => []], $form_state);
    $node->save();
  }

  /**
   * Gets the widget.
   *
   * @return \Drupal\hel_tpm_group\Plugin\Field\FieldWidget\EntityGroupFieldSelect2Widget
   *   The widget.
   */
  protected function getWidget(): EntityGroupFieldSelect2Widget {
    $widget = EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'service',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent(self::FIELD, [
      'type' => 'hel_tpm_group_entitygroupfield_select2',
      'settings' => ['required' => TRUE],
    ])->getRenderer(self::FIELD);
    assert($widget instanceof EntityGroupFieldSelect2Widget);
    return $widget;
  }

  /**
   * Creates a service in groups.
   *
   * The service is reloaded after adding the relationships, so that its group
   * field holds them.
   *
   * @param \Drupal\group\Entity\GroupInterface[] $groups
   *   The groups.
   *
   * @return \Drupal\node\NodeInterface
   *   The service.
   */
  protected function createService(array $groups): NodeInterface {
    $node = $this->createNode([
      'type' => 'service',
      'uid' => $this->orgUser->id(),
      'moderation_state' => 'draft',
    ]);
    foreach ($groups as $group) {
      $group->addRelationship($node, 'group_node:service');
    }
    return $this->reloadEntity($node);
  }

  /**
   * Loads the saved group relationships of a service.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The service.
   *
   * @return \Drupal\group\Entity\GroupRelationshipInterface[]
   *   The relationships keyed by ID.
   */
  protected function loadRelationships(NodeInterface $node): array {
    $storage = $this->entityTypeManager->getStorage('group_content');
    $storage->resetCache();
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_id', $node->id())
      ->condition('plugin_id', 'group_node:service')
      ->sort('id')
      ->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * Gets the group IDs of relationships.
   *
   * @param \Drupal\group\Entity\GroupRelationshipInterface[] $relationships
   *   The relationships.
   *
   * @return int[]
   *   The group IDs.
   */
  protected function getGroupIds(array $relationships): array {
    return array_values(array_map(
      static fn (GroupRelationshipInterface $relationship): int => (int) $relationship->getGroupId(),
      $relationships,
    ));
  }

}
