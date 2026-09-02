<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group\Form;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a hel_tpm_group form.
 */
final class ReactivateGroupMemberForm extends FormBase implements ContainerInjectionInterface {

  /**
   * Reactivate group member access service.
   *
   * @var \Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess
   */
  private ReactivateGroupMemberAccess $reactivateGroupMemberAccess;

  /**
   * {@inheritdoc}
   */
  public function __construct(RouteMatchInterface $route_match, ReactivateGroupMemberAccess $reactivate_group_member_access) {
    $this->routeMatch = $route_match;
    $this->reactivateGroupMemberAccess = $reactivate_group_member_access;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
      $container->get('hel_tpm_group.reactivate_group_member_access')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hel_tpm_group_reactivate_group_member';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if (!$this->hasAccess()) {
      return [];
    }

    $user = $this->getUser();
    if (empty($user)) {
      return [];
    }

    $form['button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Re-activate @user', ['@user' => $user->getAccountName()]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $user = $this->getUser();
    if (empty($user) || !$this->hasAccess()) {
      $form_state->setErrorByName('reactive_member', $this->t('You do not have permission to reactivate this group member.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $user = $this->getUser();
    if (empty($user)) {
      return;
    }
    $user->activate();
    $user->save();
    $this->messenger()->addMessage($this->t('Group member has been reactivated.'));
    $this->logger('hel_tpm_group')->notice('Group member %name has been reactivated by %user', [
      '%name' => $user->getDisplayName(),
      '%user' => $this->currentUser()->getDisplayName(),
    ]);
  }

  /**
   * Retrieves the user entity associated with the group membership.
   *
   * This method extracts the `group_content` parameter from the current route,
   * verifies if it is an instance of `GroupMembership`, and returns the
   * corresponding user entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The user entity if available, or null if the parameter is not a
   *   `GroupMembership` instance or `group_content` is not present.
   */
  protected function getUser() {
    $group_content = $this->routeMatch->getParameter('group_content');
    if ($group_content instanceof GroupMembership) {
      return $group_content->getEntity();
    }
  }

  /**
   * Checks if the user has access to reactivate a group member.
   *
   * This method evaluates whether the access level allows reactivating a group
   * member based on a permission check.
   *
   * @return bool
   *   TRUE if access is allowed, FALSE otherwise.
   */
  protected function hasAccess(): bool {
    return $this->reactivateGroupMemberAccess->access() instanceof AccessResultAllowed;
  }

}
