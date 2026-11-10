<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_mail_tools\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\hel_tpm_mail_tools\Utility\MessageSender;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;

/**
 * Tests that messages are rendered in the language they were sent in.
 *
 * @group hel_tpm_mail_tools
 */
final class MessageLanguageTest extends EntityKernelTestBase {

  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'node',
    'token',
    'message',
    'message_notify',
    'hel_tpm_mail_tools',
  ];

  /**
   * The message sender.
   *
   * @var \Drupal\hel_tpm_mail_tools\Utility\MessageSender
   */
  private MessageSender $messageSender;

  /**
   * The node referenced by the messages.
   *
   * @var \Drupal\node\NodeInterface
   */
  private NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('message');
    $this->installConfig(['system', 'filter', 'language']);
    $this->config('system.site')->set('mail', 'admin@example.com')->save();

    // Mirror the site language order: Finnish first, then Swedish, English.
    ConfigurableLanguage::createFromLangcode('fi')->setWeight(-10)->save();
    ConfigurableLanguage::createFromLangcode('sv')->setWeight(-9)->save();
    ConfigurableLanguage::load('en')->setWeight(-8)->save();

    NodeType::create(['type' => 'service', 'name' => 'Service'])->save();
    $this->node = Node::create([
      'type' => 'service',
      'title' => 'Finnish service',
      'langcode' => 'fi',
    ]);
    $this->node->addTranslation('en', ['title' => 'English service']);
    $this->node->save();

    FieldStorageConfig::create([
      'field_name' => 'field_node',
      'entity_type' => 'message',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
    ])->save();

    $this->createTemplate('sent_language_template', TRUE);
    $this->createTemplate('test_template', FALSE);

    $this->messageSender = $this->container->get('hel_tpm_mail_tools.utility.message_sender');
  }

  /**
   * Tests a message sent in a language is rendered in it after loading.
   */
  public function testMessageRenderedInSentLanguage(): void {
    $account = $this->createUser();
    $sent = $this->messageSender->createAndSend('sent_language_template', $account, [
      'field_node' => $this->node,
    ], NULL, 'en');
    $this->assertTrue($sent);

    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $this->assertStringContainsString('English text: English service', $mails[0]['subject']);

    $message = $this->loadMessage();
    $this->assertEquals('en', $message->language()->getId());
    $this->assertEquals('en', $message->getLanguage());
    $this->assertEquals(['English text: English service'], $this->getPlainText($message));
  }

  /**
   * Tests a message sent without a language uses the site default language.
   */
  public function testMessageWithoutLanguage(): void {
    $this->config('system.site')->set('default_langcode', 'fi')->save();

    $account = $this->createUser();
    $this->messageSender->createAndSend('sent_language_template', $account, [
      'field_node' => $this->node,
    ]);

    $message = $this->loadMessage();
    $this->assertEquals('fi', $message->language()->getId());
    $this->assertEquals(['Finnish text: Finnish service'], $this->getPlainText($message));
  }

  /**
   * Tests messages without the setting keep rendering in current language.
   */
  public function testOtherMessageRenderedInCurrentLanguage(): void {
    $account = $this->createUser();
    $this->messageSender->createAndSend('test_template', $account, [
      'field_node' => $this->node,
    ], NULL, 'en');

    $message = $this->loadMessage();
    $this->assertEquals('en', $message->language()->getId());
    $this->assertEquals(LanguageInterface::LANGCODE_NOT_SPECIFIED, $message->getLanguage());
    // The Message module default: the template text follows the current
    // language (English) and the node falls back to the first language by
    // weight (Finnish).
    $this->assertEquals(['English text: Finnish service'], $this->getPlainText($message));
  }

  /**
   * Creates a message template with an English translation.
   *
   * @param string $template
   *   The message template ID.
   * @param bool $keep_sent_language
   *   Whether stored messages are rendered in the language they were sent in.
   */
  private function createTemplate(string $template, bool $keep_sent_language): void {
    MessageTemplate::create([
      'template' => $template,
      'label' => $template,
      'langcode' => 'fi',
      'text' => [
        ['value' => 'Finnish text: [message:field_node:entity:title]', 'format' => 'plain_text'],
      ],
      'settings' => [
        'token options' => [
          'clear' => FALSE,
          'token replace' => TRUE,
        ],
      ],
      'third_party_settings' => [
        'hel_tpm_mail_tools' => [
          'keep_sent_language' => $keep_sent_language,
        ],
      ],
    ])->save();
    $this->container->get('language_manager')
      ->getLanguageConfigOverride('en', 'message.template.' . $template)
      ->set('text', [
        ['value' => 'English text: [message:field_node:entity:title]', 'format' => 'plain_text'],
      ])
      ->save();

    FieldConfig::create([
      'field_name' => 'field_node',
      'entity_type' => 'message',
      'bundle' => $template,
    ])->save();
  }

  /**
   * Loads the only message from the storage, bypassing the static cache.
   *
   * @return \Drupal\message\Entity\Message
   *   The message.
   */
  private function loadMessage(): Message {
    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    $this->assertCount(1, $ids);
    return $storage->loadUnchanged(reset($ids));
  }

  /**
   * Returns the message text partials without markup.
   *
   * @param \Drupal\message\Entity\Message $message
   *   The message.
   *
   * @return string[]
   *   The text partials.
   */
  private function getPlainText(Message $message): array {
    return array_map(fn ($partial) => trim(strip_tags((string) $partial)), $message->getText());
  }

}
