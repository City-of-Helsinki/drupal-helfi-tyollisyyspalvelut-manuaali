<?php

namespace Drupal\Tests\hel_tpm_general\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the create user administration page.
 *
 * @group user
 */
class UsernameGenerateTest extends BrowserTestBase {

  protected static $modules = [
    'hel_tpm_general',
    'group'
  ];
  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests user creation and display from the administration interface.
   */
  public function testUsernameGeneration() {
    $user = $this->drupalCreateUser(['administer users']);
    $this->drupalLogin($user);

    $this->assertEquals(\Drupal::time()->getRequestTime(), $user->getCreatedTime(), 'Creating a user sets default "created" timestamp.');


    // Test user creation page for valid fields.
    $this->drupalGet('admin/people/create');
    $this->assertSession()->fieldValueEquals('edit-status-0', '1');
    $this->assertSession()->fieldValueEquals('edit-status-1', '1');
    $this->assertSession()->checkboxChecked('edit-status-1');

    // Test that browser autocomplete behavior does not occur.
    $this->assertSession()->responseNotContains('data-user-info-from-browser');

    // Test that the password strength indicator displays.
    $config = $this->config('user.settings');

    $config->set('password_strength', TRUE)->save();
    $this->drupalGet('admin/people/create');
    $this->assertSession()->responseContains("Password strength:");

    $config->set('password_strength', FALSE)->save();
    $this->drupalGet('admin/people/create');
    $this->assertSession()->responseNotContains("Password strength:");

    // We create two users, notifying one and not notifying the other, to
    // ensure that the tests work in both cases.
    $name = $this->randomMachineName();
    $edit = [
      'mail' => $name . '@example.com',
      'pass[pass1]' => $pass = $this->randomString(),
      'pass[pass2]' => $pass,
      'notify' => FALSE,
    ];
    $this->drupalGet('admin/people/create');
    $this->submitForm($edit, 'Create new account');

    $this->drupalGet('admin/people');
    $this->assertSession()->pageTextContains($name);
    $user = user_load_by_name($name);

    $this->assertEquals($name, $user->label());
    $this->assertTrue($user->isActive(), 'User is not blocked');

    // Validate duplicate usernames are suffixed with integer.
    $edit = [
      'mail' => $name . '@example-1.com',
      'pass[pass1]' => $pass,
      'pass[pass2]' => $pass,
      'notify' => FALSE,
    ];

    $generated_name = $name . '-0';

    $this->drupalGet('admin/people/create');
    $this->submitForm($edit, 'Create new account');
    $this->assertSession()->pageTextContains("Created a new user account for $generated_name. No email has been sent");
    $this->assertSession()->pageTextNotContains('Password field is required');
  }

}
