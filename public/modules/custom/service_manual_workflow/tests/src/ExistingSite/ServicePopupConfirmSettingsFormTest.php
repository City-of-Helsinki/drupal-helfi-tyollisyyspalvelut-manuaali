<?php

namespace Drupal\Tests\service_manual_workflow\ExistingSite;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test the service popup confirm settings admin form.
 *
 * @group service_manual_workflow
 */
class ServicePopupConfirmSettingsFormTest extends ExistingSiteBase {

  /**
   * Test service popup confirm settings form.
   */
  public function testSettingsForm() {
    $langcode = 'en';
    $language = \Drupal::languageManager()->getLanguage($langcode);
    $rid = $this->createAdminRole();
    $user = $this->createUser(['administer site configuration']);
    $user->addRole($rid);
    $this->drupalLogin($user, $langcode);

    $this->drupalGet('/admin/config/system/service-popup-confirm-settings', ['language' => $language]);
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
  }

  /**
   * Log in using account email and a given langcode.
   *
   * @todo Move to trait.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param string $langcode
   *   The langcode.
   * @param string $submitButtonText
   *   The login form submit button value.
   *
   * @return void
   *   Void.
   */
  protected function drupalLogin(AccountInterface $account, string $langcode = 'en', string $submitButtonText = 'Log in'): void {
    $language = \Drupal::languageManager()->getLanguage($langcode);
    $this->drupalGet(Url::fromRoute('user.login', [], ['language' => $language]));
    $this->submitForm([
      'name' => $account->getEmail(),
      'pass' => $account->passRaw,
    ], $submitButtonText);

    $account->sessionId = $this->getSession()->getCookie(\Drupal::service('session_configuration')->getOptions(\Drupal::request())['name']);
    $this->loggedInUser = $account;
    $this->container->get('current_user')->setAccount($account);
  }

}
