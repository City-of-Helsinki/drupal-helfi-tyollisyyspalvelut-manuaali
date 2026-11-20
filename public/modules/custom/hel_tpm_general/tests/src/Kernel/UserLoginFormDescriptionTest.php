<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_general\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Form\UserLoginForm;

/**
 * Tests that the user login form has no field descriptions.
 *
 * @group hel_tpm_general
 */
final class UserLoginFormDescriptionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'purge',
    'entity',
    'flexible_permissions',
    'options',
    'group',
    'mail_login',
    'hel_tpm_general',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['mail_login']);
  }

  /**
   * Tests that the descriptions set by mail_login are removed.
   */
  public function testDescriptionsRemoved(): void {
    $this->config('mail_login.settings')
      ->set('mail_login_enabled', 1)
      ->set('mail_login_email_only', 1)
      ->set('mail_login_override_login_labels', 1)
      ->set('mail_login_email_only_title', 'Email login title')
      ->save();

    $form = $this->container->get('form_builder')->getForm(UserLoginForm::class);

    $this->assertSame('Email login title', (string) $form['name']['#title']);
    $this->assertArrayNotHasKey('#description', $form['name']);
    $this->assertArrayNotHasKey('#description', $form['pass']);
  }

}
