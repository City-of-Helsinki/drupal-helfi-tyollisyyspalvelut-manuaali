<?php

namespace Drupal\hel_tpm_group_invite\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ginvite\Form\BulkGroupInvitationConfirm;
use Drupal\group\Entity\GroupContent;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Bulk group invitation custom confirm form class.
 */
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
      'finished' => 'Drupal\hel_tpm_group_invite\Form\BulkGroupInvitationCustomConfirm::batchFinished',
    ];
    $roles = $this->tempstore['roles'];
    foreach ($this->tempstore['emails'] as $email) {
      $values = [
        'type' => $this->tempstore['plugin'],
        'gid' => $this->tempstore['gid'],
        'invitee_mail' => $email,
        'entity_id' => 0,
        'group_roles' => $roles,
      ];
      $batch['operations'][] = [
        __CLASS__ . '::batchCreateInvite',
        [$values],
      ];
    }

    batch_set($batch);
  }

  /**
   * Create group invites in batch.
   *
   * @param array $values
   *   Array of emails used to invite people.
   * @param array $context
   *   Batch context.
   *
   * @return void
   *   Return nothing.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function batchCreateInvite($values, &$context) {
    self::createInvitee($values['invitee_mail']);
    self::createMembership($values);
  }

  /**
   * Create membership for invitee.
   *
   * @param array $values
   *   Array of invitation data.
   *
   * @return void
   *   Return nothing.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function createMembership($values) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $group = $entity_type_manager->getStorage('group')->load($values['gid']);
    $account = $entity_type_manager->getStorage('user')->loadByProperties(['mail' => $values['invitee_mail']]);
    $account = reset($account);
    $current_user = \Drupal::currentUser();

    $contentTypeConfigId = $group
      ->getGroupType()
      ->getContentPlugin('group_membership')
      ->getContentTypeConfigId();

    $roles = [];
    foreach ($values['group_roles'] as $rid => $value) {
      $roles[]['target_id'] = $rid;
    }

    $group_membership = GroupContent::create([
      'type' => $contentTypeConfigId,
      'entity_id' => $account->id(),
      'content_plugin' => 'group_membership',
      'gid' => $group->id(),
      'uid' => $current_user->id(),
      'group_roles' => $roles,
    ]);

    $group_membership->save();
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      try {
        $tempstore = \Drupal::service('tempstore.private')->get('ginvite_bulk_invitation');
        $destination = new Url('view.group_members.page_1', ['group' => $tempstore->get('params')['gid']]);
        $redirect = new RedirectResponse($destination->toString());
        $tempstore->delete('params');
        $redirect->send();
      }
      catch (\Exception $error) {
        \Drupal::service('logger.factory')->get('ginvite')->alert(new TranslatableMarkup('@err', ['@err' => $error]));
      }

    }
    else {
      $error_operation = reset($operations);
      \Drupal::service('messenger')->addMessage(new TranslatableMarkup('An error occurred while processing @operation with arguments : @args', [
        '@operation' => $error_operation[0],
        '@args' => print_r($error_operation[0]),
      ]));
    }
  }

  /**
   * Creates new user.
   *
   * @param string $email
   *   Invitee email.
   *
   * @return void
   *   Return nothing.
   *
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
      'status' => 1,
    ]);
    $user->save();
    _user_mail_notify('register_no_approval_required', $user);
  }

}
