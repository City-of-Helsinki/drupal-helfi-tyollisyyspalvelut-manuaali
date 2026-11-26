<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;

/**
 * Hook implementations for the user login form.
 */
final class UserLoginFormHooks {

  /**
   * Removes the username and password field descriptions.
   *
   * The mail_login module sets these descriptions from its configuration, so
   * this must run after mail_login's form alter.
   */
  #[Hook('form_user_login_form_alter', order: Order::Last)]
  public function formUserLoginFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    unset($form['name']['#description'], $form['pass']['#description']);
  }

}
