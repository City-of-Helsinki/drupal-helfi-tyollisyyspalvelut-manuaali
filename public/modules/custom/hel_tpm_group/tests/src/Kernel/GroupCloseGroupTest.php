<?php

declare(strict_types = 1);

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\Group;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\system\Entity\Action;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\hel_tpm_group\Traits\GroupInitTrait;
use Drupal\Tests\service_manual_workflow\Traits\ServiceManualWorkflowTestTrait;

/**
 * Tests field reference for group user selection.
 *
 * @coversDefaultClass \Drupal\hel_tpm_group\Plugin\EntityReferenceSelection\GroupUserSelection
 *
 * @group hel_tpm_group
 */
class GroupCloseGroupTest extends GroupKernelTestBase {

  use EntityReferenceFieldCreationTrait;
  use ContentModerationTestTrait;
  use GroupInitTrait;
  use ServiceManualWorkflowTestTrait;


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
    'service_manual_workflow',
    'service_manual_workflow_service_test',
  ];

  /**
   * @var mixed
   */
  private mixed $messenger;

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

    $module_configs = [
      'content_moderation',
      'gcontent_moderation',
      'group',
      'workflows',
      'hel_tpm_group',
      'service_manual_workflow_service_test'
    ];
    $this->installConfig($module_configs);

    $this->messenger = \Drupal::service('messenger');
    ConfigurableLanguage::createFromLangcode('fi')->save();
  }

  /**
   * Tests field in group node has group users.
   *
   * @return void
   *   -
   */
  public function testClosingOfGroup(): void {
    $this->initGroups(FALSE);
    $node = $this->createGroupContent($this->orgGroup, FALSE);
    $node->set('field_service_producer', $this->orgGroup);
    $node->set('field_service_provider_updatee', $this->orgUser);
    $node->set('field_responsible_municipality', $this->orgGroup);
    $node->set('field_responsible_updatee', $this->orgUser);
    $node->save();

    $node2 = $this->createGroupContent($this->orgGroup, FALSE);
    $node2->set('field_service_producer', $this->orgGroup);
    $node2->set('field_service_provider_updatee', $this->orgUser);
    $node2->set('field_responsible_municipality', $this->orgGroup);
    $node2->set('field_responsible_updatee', $this->orgUser);
    $node2->save();

    $translation = $node->addTranslation('fi');
    $translation->setTitle('Translation');
    $translation->set('moderation_state', 'draft');
    $translation->save();

    $action = Action::create([
      'id' => 'hel_tpm_group_close_group',
      'label' => $this->randomMachineName(),
      'type' => 'group',
      'plugin' => 'hel_tpm_group_close_group',
    ]);
    $action->execute([$this->orgGroup]);

    $node = $this->reloadEntity($node);
    $this->assertEquals('archived', $node->get('moderation_state')->value);
    $this->assertEquals(FALSE, $node->isPublished());

    $translation = $this->reloadEntity($translation);
    $this->assertEquals('archived', $translation->get('moderation_state')->value);

    $node2 = $this->reloadEntity($node2);
    $this->assertEquals('archived', $node2->get('moderation_state')->value);

    $this->assertEmpty($this->orgGroup->getMembers());

    // Check that all field references all removed.
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery();
    $or = $query->orConditionGroup()
      ->condition('field_responsible_municipality', $this->orgGroup->id())
      ->condition('field_service_producer', $this->orgGroup->id());
    $query->condition($or);
    $query->accessCheck(FALSE);
    $this->assertEmpty($query->execute());

    // Confirm group is unpublished.
    $group = $this->reloadEntity($this->orgGroup);
    $this->assertEquals(FALSE, $group->isPublished());
  }

  /**
   * Validate that user can't close group with subgroups.
   *
   * @return void
   *  -
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testGroupClosingValidation() {
    $this->initGroups(TRUE);
    $action = Action::create([
      'id' => 'hel_tpm_group_close_group',
      'label' => $this->randomMachineName(),
      'type' => 'group',
      'plugin' => 'hel_tpm_group_close_group',
    ]);
    $action->execute([$this->orgGroup]);

    $messages = $this->messenger->messagesByType(MessengerInterface::TYPE_ERROR);
    $this->assertEquals(
      new TranslatableMarkup('Skipped @group because it has subgroup(s)', [
        '@group' => $this->orgGroup->label()]
      ), $messages[0]);

    $group = $this->reloadEntity($this->orgGroup);
    $this->assertEquals(TRUE, $group->isPublished());
  }

}
