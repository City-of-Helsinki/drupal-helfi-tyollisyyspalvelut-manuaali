<?php

namespace Drupal\hel_tpm_group_invite\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ginvite\Form\BulkGroupInvitation;
use Drupal\group\Entity\GroupInterface;

/**
 * Bulk group invitation custom class.
 */
class BulkGroupInvitationCustom extends BulkGroupInvitation {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $group = \Drupal::service('current_route_match')->getParameter('group');

    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => t('Roles'),
      '#options' => self::getGroupRoleOptions($group),
      '#weight' => 0,
      '#attributes' => ['id' => 'edit-roles'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * Fetch group roles for select options.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group object.
   *
   * @return array
   *   Array of available group roles.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getGroupRoleOptions(GroupInterface $group) {
    $roles = [];
    $bl = ['service_provider-organization_ad'];

    $group_roles = $this->entityTypeManager->getStorage('group_role')->loadByProperties([
      'group_type' => $group->getGroupType()->id(),
      'scope' => 'individual'
    ]);

    if (empty($group_roles)) {
      return $roles;
    }
    foreach ($group_roles as $role) {
      if (in_array($role->id(), $bl)) {
        continue;
      }
      $roles[$role->id()] = $role->label();
    }
    return $roles;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Prepare params to store them in tempstore.
    $params['gid'] = $this->group->id();
    $params['plugin'] = $this->group->getGroupType()->getContentPlugin('group_invitation')->getContentTypeConfigId();
    $params['emails'] = $this->getSubmittedEmails($form_state);
    $params['roles'] = $this->getRoles($form_state);

    $tempstore = $this->tempStoreFactory->get('ginvite_bulk_invitation');

    try {
      $tempstore->set('params', $params);
      // Redirect to confirm form.
      $form_state->setRedirect('ginvite.invitation.bulk.confirm', ['group' => $this->group->id()]);
    }
    catch (\Exception $error) {
      $this->loggerFactory->get('ginvite')->alert($this->t('@err', ['@err' => $error]));
      $this->messenger->addWarning($this->t('Unable to proceed, please try again.'));
    }
  }

  /**
   * Get selected role(s).
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state interface.
   *
   * @return array
   *   Array of roles.
   */
  protected function getRoles(FormStateInterface $form_state) : array {
    $roles = $form_state->getValue('roles');
    if (empty($roles)) {
      return [];
    }
    foreach ($roles as $key => $role) {
      if ($key === $role) {
        continue;
      }
      unset($roles[$key]);
    }
    return $roles;
  }

  /**
   * Get array of submited emails.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   List of emails to invite .
   */
  private function getSubmittedEmails(FormStateInterface $form_state) {
    return array_map('trim', array_unique(explode("\r\n", trim($form_state->getValue('email_address')))));
  }

}
