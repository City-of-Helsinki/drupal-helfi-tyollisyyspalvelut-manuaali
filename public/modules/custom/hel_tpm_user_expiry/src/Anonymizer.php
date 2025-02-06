<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_user_expiry;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\Core\State\State;
use Drupal\group\Entity\GroupMembership;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * User anonymizer service.
 */
final class Anonymizer {

  use LoggerChannelTrait;

  /**
   * Logger interface.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * Constructs an Anonymization object.
   */
  public function __construct(
    private readonly PasswordGeneratorInterface $passwordGenerator,
    private readonly State $state,
  ) {
    $this->logger = $this->getLogger('hel_tpm_user_expiry_anonymizer');
  }

  /**
   * Anonymize inactive and blocked user.
   *
   * @param \Drupal\user\UserInterface $user
   *   User interface.
   * @param bool $force
   *   Bool to force anonymization without last access check.
   *
   * @return bool
   *   TRUE when successful, FALSE otherwise.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Random\RandomException
   */
  public function anonymizeUser(UserInterface $user, bool $force = FALSE): bool {
    // Perform extra checks before anonymizing user data.
    if (!$user->isBlocked()
      || in_array($user->id(), [0, 1])) {
      return FALSE;
    }

    // Check last access if check hasn't been overridden.
    if ($force === FALSE && $user->get('access')->value >= strtotime('-210 days')) {
      return FALSE;
    }

    // Anonymize user data.
    // Setting the email will also change the username.
    // See hel_tpm_general.module for more information.
    $user->setEmail('anonymous-' . $user->id() . '-' . random_int(100000, 999999) . '@anonymous.invalid');
    $user->setPassword($this->passwordGenerator->generate(20));
    $user->set('field_name', '');
    $user->set('field_job_title', '');
    $user->set('field_employer', '');
    foreach ($user->getRoles() as $role) {
      $user->removeRole($role);
    }

    if (count($user->validate())) {
      $this->logger->error('Anonymization of user %user failed for validation errors.', ['%user' => $user->id()]);
      return FALSE;
    }
    $user->save();

    // After anonymization remove user group memberships.
    $this->removeGroupMemberships($user);

    // Store anonymized user IDs using State API.
    if (is_array($anonymized_users = $this->state->get('hel_tpm_user_expiry.anonymized_users'))) {
      $anonymized_users[] = $user->id();
    }
    else {
      $anonymized_users = [$user->id()];
    }
    $this->state->set('hel_tpm_user_expiry.anonymized_users', $anonymized_users);

    $this->logger->info('Anonymized inactive and blocked user %user.', ['%user' => $user->id()]);
    return TRUE;
  }

  /**
   * Remove group memberships from user.
   *
   * @param \Drupal\user\UserInterface $user
   *   User account interface.
   *
   * @return void
   *   Void.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function removeGroupMemberships(UserInterface $user) {
    $memberships = GroupMembership::loadByUser($user);
    foreach ($memberships as $membership) {
      $membership->delete();
    }
  }

}
