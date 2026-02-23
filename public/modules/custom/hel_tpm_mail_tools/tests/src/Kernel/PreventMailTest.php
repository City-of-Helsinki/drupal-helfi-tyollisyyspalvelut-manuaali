<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_mail_tools\Kernel;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\hel_tpm_mail_tools\Utility\PreventMailUtility;

/**
 * Tests preventing sending mails.
 *
 * @group hel_tpm_mail_tools
 */
final class PreventMailTest extends EntityKernelTestBase {

  use UserCreationTrait;

  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'message',
    'message_notify',
    'hel_tpm_mail_tools',
  ];

  /**
   * The plugin manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  private $pluginManager;

  /**
   * Mail configuration array.
   *
   * @var array
   */
  private $configuration;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->config('system.site')->set('mail', 'admin@example.com')->save();
    /** @var \Drupal\Core\Action\ActionManager $plugin_manager */
    $this->pluginManager = $this->container->get('plugin.manager.action');
    $this->configuration = [
      'recipient' => 'test@example.com',
      'subject' => 'Test subject',
      'message' => 'Test message',
    ];
  }

  /**
   * Tests sending mail with default settings.
   */
  public function testSendingMailWithDefaults() {
    $this->pluginManager
      ->createInstance('action_send_email_action', $this->configuration)
      ->execute();
    $this->assertCount(1, $this->getMails());
  }

  /**
   * Tests sending mail with blocking mail setting.
   */
  public function testSendingMailWithPrevent() {
    PreventMailUtility::blockMail();
    $this->pluginManager
      ->createInstance('action_send_email_action', $this->configuration)
      ->execute();
    $this->assertCount(0, $this->getMails());
  }

  /**
   * Tests sending mail with non-blocking mail setting.
   */
  public function testSendingMailWithNoPrevent() {
    PreventMailUtility::blockMail(FALSE);
    $this->pluginManager
      ->createInstance('action_send_email_action', $this->configuration)
      ->execute();
    $this->assertCount(1, $this->getMails());
  }

}
