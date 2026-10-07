<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\Hook;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Extension\ProceduralCall;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\RemoveHook;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Hook\NodeEntityHooks;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds a user cancel method that deletes the account but keeps its content.
 *
 * Core deletes all nodes of a user when the user entity is deleted and Group
 * deletes all groups owned by the user. The "user_cancel_reassign" method
 * avoids this by first reassigning everything to the anonymous user, which
 * updates every revision of the content. This method deletes the account
 * without touching the content: the content keeps its original owner ID.
 *
 * The method is processed as core "user_cancel_delete" while the account is
 * flagged, and the node and group delete hooks are skipped for flagged
 * accounts.
 */
#[RemoveHook('user_predelete', class: NodeEntityHooks::class, method: 'userPredelete')]
#[RemoveHook('user_delete', class: ProceduralCall::class, method: 'group_user_delete')]
class UserCancelHooks {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The account cancellation method ID.
   */
  public const string METHOD = 'hel_tpm_general_delete_keep_content';

  /**
   * How long an account stays flagged, in seconds.
   *
   * The flag is removed when the account is deleted. The expiry only cleans
   * up flags left behind by an interrupted batch.
   */
  protected const int FLAG_EXPIRE = 86400;

  /**
   * Constructs a UserCancelHooks object.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $keyValueExpirableFactory
   *   The expirable key-value store factory.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $classResolver
   *   The class resolver.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    #[Autowire(service: 'keyvalue.expirable')]
    protected KeyValueExpirableFactoryInterface $keyValueExpirableFactory,
    #[Autowire(service: 'class_resolver')]
    protected ClassResolverInterface $classResolver,
    #[Autowire(service: 'current_user')]
    protected AccountInterface $currentUser,
  ) {}

  /**
   * Implements hook_user_cancel_methods_alter().
   */
  #[Hook('user_cancel_methods_alter')]
  public function userCancelMethodsAlter(array &$methods): void {
    $methods[self::METHOD] = [
      'title' => $this->t('Delete the account and keep its content unchanged. This action cannot be undone.'),
      'description' => $this->t('The account will be removed and all account information deleted. Content and groups created by the account are kept as they are and are not reassigned.'),
      'access' => $this->currentUser->hasPermission('administer users'),
    ];
  }

  /**
   * Implements hook_form_FORM_ID_alter() for user_cancel_form.
   */
  #[Hook('form_user_cancel_form_alter')]
  public function formUserCancelFormAlter(array &$form, FormStateInterface $form_state): void {
    $this->addHandlers($form);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for user_multiple_cancel_confirm.
   */
  #[Hook('form_user_multiple_cancel_confirm_alter')]
  public function formUserMultipleCancelConfirmAlter(array &$form, FormStateInterface $form_state): void {
    $this->addHandlers($form);
  }

  /**
   * Validates that the method is used only for immediate cancellation.
   *
   * Cancellation confirmed by email runs later from the stored method, so it
   * is not supported. Own account is always cancelled with confirmation.
   */
  public function validateCancelMethod(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('user_cancel_method') !== self::METHOD) {
      return;
    }
    $uids = $this->getAccountIds($form_state);
    if (in_array((int) $this->currentUser->id(), $uids, TRUE)) {
      $form_state->setErrorByName('user_cancel_method', $this->t('This cancellation method cannot be used for your own account.'));
    }
    if (!$form_state->isValueEmpty('user_cancel_confirm')) {
      $form_state->setErrorByName('user_cancel_confirm', $this->t('This cancellation method cannot be used with email confirmation.'));
    }
  }

  /**
   * Flags the accounts and switches to the core delete method.
   */
  public function submitCancelMethod(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('user_cancel_method') !== self::METHOD) {
      return;
    }
    foreach ($this->getAccountIds($form_state) as $uid) {
      $this->getStore()->setWithExpire((string) $uid, TRUE, self::FLAG_EXPIRE);
    }
    $form_state->setValue('user_cancel_method', 'user_cancel_delete');
  }

  /**
   * Implements hook_ENTITY_TYPE_predelete() for user entities.
   *
   * Replaces node module's implementation, which deletes the user's nodes.
   */
  #[Hook('user_predelete')]
  public function userPredelete(UserInterface $account): void {
    if ($this->isFlagged($account)) {
      return;
    }
    $this->classResolver
      ->getInstanceFromDefinition(NodeEntityHooks::class)
      ->userPredelete($account);
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for user entities.
   *
   * Replaces group module's implementation, which deletes the user's groups.
   */
  #[Hook('user_delete')]
  public function userDelete(UserInterface $account): void {
    if ($this->isFlagged($account)) {
      $this->getStore()->delete((string) $account->id());
      return;
    }
    group_user_delete($account);
  }

  /**
   * Adds the validate and submit handlers to a user cancel form.
   *
   * The submit handler must run before the form's own submit handler.
   */
  protected function addHandlers(array &$form): void {
    $form['#validate'][] = [$this, 'validateCancelMethod'];
    $submit = [$this, 'submitCancelMethod'];
    if (isset($form['actions']['submit']['#submit'])) {
      array_unshift($form['actions']['submit']['#submit'], $submit);
    }
    $form['#submit'] ??= [];
    array_unshift($form['#submit'], $submit);
  }

  /**
   * Gets the IDs of the accounts being cancelled.
   *
   * @return int[]
   *   The user IDs.
   */
  protected function getAccountIds(FormStateInterface $form_state): array {
    // The multiple cancel form lists the accounts keyed by user ID.
    $accounts = $form_state->getValue('accounts');
    if (is_array($accounts)) {
      return array_map('intval', array_keys($accounts));
    }
    $uid = $form_state->getValue('uid');
    return $uid === NULL ? [] : [(int) $uid];
  }

  /**
   * Checks whether the account is deleted with this method.
   */
  protected function isFlagged(UserInterface $account): bool {
    return (bool) $this->getStore()->get((string) $account->id(), FALSE);
  }

  /**
   * Gets the store of flagged accounts.
   */
  protected function getStore(): KeyValueStoreExpirableInterface {
    return $this->keyValueExpirableFactory->get('hel_tpm_general.user_cancel_keep_content');
  }

}
