<?php

namespace Drupal\hel_tpm_group_invite\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ginvite\Form\BulkGroupInvitation;
use Drupal\ginvite\Form\BulkGroupInvitationConfirm;
use Drupal\group\Entity\GroupContent;
use Drupal\user\Entity\User;

class BulkGroupInvitationCustomConfirm extends BulkGroupInvitationConfirm {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = [
      'title' => $this->t('Inviting Members'),
      'operations' => [],
      'init_message'     => $this->t('Sending Invites'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message'    => $this->t('An error occurred during processing'),
      'finished' => 'Drupal\ginvite\Form\BulkGroupInvitationConfirm::batchFinished',
    ];
    $roles = $this->tempstore['roles'];
    foreach ($this->tempstore['emails'] as $email) {
      $values = [
        'type' => $this->tempstore['plugin'],
        'gid' => $this->tempstore['gid'],
        'invitee_mail' => $email,
        'entity_id' => 0,
        'group_roles' => $roles
      ];
      $batch['operations'][] = [
        __CLASS__ . '::batchCreateInvite',
        [$values],
      ];
    }

    batch_set($batch);
  }

  /**
   * @param $values
   * @param $context
   *
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function batchCreateInvite($values, &$context) {
    self::createInvitee($values['invitee_mail']);
    $invitation = GroupContent::create($values);
    if (!empty($values['group_roles'])) {
      $roles = [];
      foreach ($values['group_roles'] as $group_role) {
        $roles[] = ['target_id' => $group_role];
      }
      $invitation->set('group_roles', $roles);
    }

    $invitation->save();
  }

  /**
   * @param $email
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private static function createInvitee($email) {
    $user = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $email]);
    if (!empty($user)) {
      return;
    }
    $user = User::create([
      'name' => $email,
      'mail' => $email,
      'status' => 1
    ]);
    $user->save();
    _user_mail_notify('register_no_approval_required', $user);
  }
}
