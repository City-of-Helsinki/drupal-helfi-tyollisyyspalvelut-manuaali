<?php

/**
* Implements hook_token_info_alter().
*/
function hel_tpm_user_expiry_token_info() {
  $tokens['one-time-login-expiry'] = [
    'name' => t('One time login expiration in Days'),
    'description' => t('Token to show how long one time login link is valid')
  ];
  return ['tokens' => ['user' => $tokens]];
}

/**
 * Implements hook_tokens().
 */
function hel_tpm_user_expiry_tokens($type, $tokens, array $data, array $options, \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata) {
  if ($type !== 'user') {
    return;
  }

  foreach ($tokens as $name => $token) {
    if ($name === 'one-time-login-expiry') {
      // Create token from password_reset_timeout config.
      $reset_timeout = Drupal::config('user.settings')->get('password_reset_timeout');
      $replacements[$token] = $reset_timeout / 86400;
    }
  }

  return $replacements;
}