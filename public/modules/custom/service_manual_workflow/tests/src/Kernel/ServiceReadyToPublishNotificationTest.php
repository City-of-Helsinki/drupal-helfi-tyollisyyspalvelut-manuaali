<?php

namespace Drupal\Tests\service_manual_workflow\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\service_manual_workflow\Traits\ServiceManualWorkflowTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the ready to publish notification of translated services.
 *
 * @covers \Drupal\service_manual_workflow\EventSubscriber\ServiceStateChangedNotificationSubscriber
 */
class ServiceReadyToPublishNotificationTest extends GroupKernelTestBase {

  use ServiceManualWorkflowTestTrait;

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'content_translation',
    'filter',
    'language',
    'gnode',
    'workflows',
    'hel_tpm_group',
    'hel_tpm_mail_tools',
    'node',
    'field_permissions',
    'flexible_permissions',
    'gcontent_moderation',
    'message',
    'message_notify',
    'message_notify_test',
    'service_manual_workflow',
    'service_manual_workflow_service_test',
    'service_manual_workflow_notification_test_config',
    'ggroup',
    'ggroup_role_mapper',
    'token',
    'inline_entity_form',
    'ief_table_view_mode',
    'purge',
    'hel_tpm_general',
  ];

  /**
   * Service provider group.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  private $spGroup;

  /**
   * Service provider user.
   *
   * @var \Drupal\Core\Entity\EntityInterface|\Drupal\user\Entity\User
   */
  private $spUser;

  /**
   * Responsible updatee user.
   *
   * @var \Drupal\Core\Entity\EntityInterface|\Drupal\user\Entity\User
   */
  private $updateeUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('message');
    $this->installSchema('ggroup', ['group_graph']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field', 'filter', 'node', 'system', 'language', 'message_notify']);
    $this->installConfig([
      'service_manual_workflow_service_test',
      'service_manual_workflow_notification_test_config',
      'content_moderation',
    ]);

    ConfigurableLanguage::createFromLangcode('fi')->save();
    $this->config('language.negotiation')
      ->set('url.prefixes', ['fi' => 'fi', 'en' => 'en'])
      ->save();
    $this->config('language.types')
      ->set('negotiation.language_interface.enabled', [
        'language-url' => 0,
        'language-selected' => 1,
      ])
      ->save();
    // Register the language path processor for the URL prefixes.
    $this->container->get('kernel')->rebuildContainer();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'service', TRUE);

    // Like on the site, the ready to publish message links to the latest
    // version and the published message to the published service.
    $url_tokens = [
      'group_ready_to_publish_notificat' => '[message:field_node:entity:latest-revision-url]',
      'content_has_been_published' => '[message:field_node:entity:url]',
    ];
    foreach ($url_tokens as $template => $url_token) {
      // Like on the site, the message text is the same in all languages.
      $text = [
        [
          'value' => 'Message text: [message:field_node:entity:title]',
          'format' => 'plain_text',
        ],
        [
          'value' => 'Message text: ' . $url_token,
          'format' => 'plain_text',
        ],
      ];

      // Mail subject and body view displays.
      foreach (['mail_subject' => 0, 'mail_body' => 1] as $view_mode => $delta) {
        EntityViewDisplay::load('message.' . $template . '.' . $view_mode)
          ->setComponent('partial_' . $delta, ['weight' => 0])
          ->save();
      }

      $config_name = 'message.template.' . $template;
      $this->config($config_name)->set('text', $text)->save();
      \Drupal::languageManager()->getLanguageConfigOverride('fi', $config_name)
        ->set('text', $text)
        ->save();
    }

    $current_user = $this->getCurrentUser();
    $current_user->set('mail', \Drupal::config('system.site')->get('mail'));
    $current_user->save();

    $this->spUser = $this->createUserWithRoles(['editor']);
    $this->spGroup = $this->createGroup(['type' => 'service_provider']);
    $this->spGroup->addMember($this->spUser, ['group_roles' => 'service_provider-editor']);

    $this->updateeUser = $this->createUserWithRoles(['specialist editor', 'editor']);
  }

  /**
   * Tests the node tokens use the translation that was set ready to publish.
   */
  public function testTranslatedServiceReadyToPublishNotification() {
    $node = $this->createNode([
      'type' => 'service',
      'langcode' => 'fi',
      'title' => 'Finnish service',
      'moderation_state' => 'draft',
    ]);
    $node->set('field_responsible_updatee', $this->updateeUser);
    $node->save();
    $this->spGroup->addRelationship($node, 'group_node:service');

    $translation = $node->addTranslation('en', [
      'title' => 'English service',
      'moderation_state' => 'draft',
    ]);
    $translation->save();

    $translation->set('moderation_state', 'ready_to_publish');
    $translation->save();

    $mails = $this->getMails(['id' => 'message_notify_group_ready_to_publish_notificat']);
    $this->assertCount(1, $mails);
    $mail = reset($mails);
    $this->assertEquals($this->updateeUser->getEmail(), $mail['to']);

    $this->assertEquals('Message text: English service', $mail['subject']);
    $this->assertStringContainsString('Message text: ', $mail['body']);
    // The translation has never been published, so its drafts are default
    // revisions and the latest version route would be forbidden.
    $this->assertStringContainsString($translation->toUrl('canonical', ['absolute' => TRUE])->toString(), $mail['body']);
    $this->assertStringNotContainsString('/latest', $mail['body']);
    $this->assertStringNotContainsString($node->getTranslation('fi')->toUrl('canonical', ['absolute' => TRUE])->toString(), $mail['body']);

    // Setting the original translation ready to publish uses Finnish tokens.
    $original = $node->getTranslation('fi');
    $original->set('moderation_state', 'ready_to_publish');
    $original->save();

    $mails = $this->getMails(['id' => 'message_notify_group_ready_to_publish_notificat']);
    $this->assertCount(2, $mails);
    $this->assertEquals('Message text: Finnish service', end($mails)['subject']);

    // Once published, new drafts are pending revisions and the link points to
    // the latest version.
    $original->set('moderation_state', 'published');
    $original->save();
    $original->set('moderation_state', 'draft');
    $original->save();
    $original->set('moderation_state', 'ready_to_publish');
    $original->save();

    $mails = $this->getMails(['id' => 'message_notify_group_ready_to_publish_notificat']);
    $this->assertCount(3, $mails);
    $this->assertStringContainsString($original->toUrl('latest-version', ['absolute' => TRUE])->toString(), end($mails)['body']);
  }

  /**
   * Tests the node tokens use the translation that was published.
   */
  public function testTranslatedServicePublishedNotification() {
    $node = $this->createNode([
      'type' => 'service',
      'langcode' => 'fi',
      'title' => 'Finnish service',
      'moderation_state' => 'ready_to_publish',
    ]);
    $node->set('field_responsible_updatee', $this->updateeUser);
    $node->set('field_service_provider_updatee', $this->spUser);
    $node->save();
    $this->spGroup->addRelationship($node, 'group_node:service');

    $translation = $node->addTranslation('en', [
      'title' => 'English service',
      'moderation_state' => 'ready_to_publish',
    ]);
    $translation->save();

    $original = $node->getTranslation('fi');
    $original->set('moderation_state', 'published');
    $original->save();
    $translation->set('moderation_state', 'published');
    $translation->save();

    $mails = $this->getMails(['id' => 'message_notify_content_has_been_published']);
    $this->assertCount(2, $mails);
    $this->assertEquals($this->spUser->getEmail(), $mails[0]['to']);
    $this->assertEquals('Message text: Finnish service', $mails[0]['subject']);
    $this->assertEquals($this->spUser->getEmail(), $mails[1]['to']);
    $this->assertEquals('Message text: English service', $mails[1]['subject']);
    $this->assertStringContainsString($translation->toUrl('canonical', ['absolute' => TRUE])->toString(), $mails[1]['body']);
  }

}
