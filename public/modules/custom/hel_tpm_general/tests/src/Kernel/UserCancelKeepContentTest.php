<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_general\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\hel_tpm_general\Hook\UserCancelHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;

/**
 * Tests the account cancel method that keeps the content.
 *
 * @group hel_tpm_general
 */
class UserCancelKeepContentTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'filter',
    'text',
    'options',
    'entity',
    'flexible_permissions',
    'group',
    'inline_entity_form',
    'ief_table_view_mode',
    // Required by the hel_tpm_general.purge.queue.txbufferunique decorator.
    'purge',
    'hel_tpm_general',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_content');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['filter', 'node', 'user']);
    $this->createContentType(['type' => 'page']);
    // Reserve user ID 1.
    $this->createAccount();
  }

  /**
   * Tests the cancel method is available for user administrators only.
   */
  public function testCancelMethodAccess(): void {
    $this->setUpCurrentUser($this->accountValues(), ['administer users']);
    $methods = user_cancel_methods();
    $this->assertArrayHasKey(UserCancelHooks::METHOD, $methods['#options']);
    $this->assertTrue($methods[UserCancelHooks::METHOD]['#access']);

    $this->setUpCurrentUser($this->accountValues());
    $methods = user_cancel_methods();
    $this->assertFalse($methods[UserCancelHooks::METHOD]['#access']);
  }

  /**
   * Tests that the content is kept when the account is deleted.
   */
  public function testDeleteKeepsContent(): void {
    $this->setUpCurrentUser($this->accountValues(), ['administer users']);
    $account = $this->createAccount();
    $node = $this->createNodeFor((int) $account->id());

    $form = [];
    $form_state = (new FormState())->setValues([
      'uid' => $account->id(),
      'user_cancel_method' => UserCancelHooks::METHOD,
    ]);
    $hooks = $this->container->get(UserCancelHooks::class);
    $hooks->validateCancelMethod($form, $form_state);
    $this->assertEmpty($form_state->getErrors());
    $hooks->submitCancelMethod($form, $form_state);
    $this->assertSame('user_cancel_delete', $form_state->getValue('user_cancel_method'));

    $account->delete();

    $node = Node::load($node->id());
    $this->assertNotNull($node);
    $this->assertSame((int) $account->id(), (int) $node->getOwnerId());
    $flag = $this->container->get('keyvalue.expirable')
      ->get('hel_tpm_general.user_cancel_keep_content')
      ->get((string) $account->id());
    $this->assertNull($flag);
  }

  /**
   * Tests that the core delete method still deletes the content.
   */
  public function testDeleteWithoutFlagDeletesContent(): void {
    $account = $this->createAccount();
    $node = $this->createNodeFor((int) $account->id());

    $account->delete();

    $this->assertNull(Node::load($node->id()));
  }

  /**
   * Tests the method is refused for own account and email confirmation.
   */
  public function testValidation(): void {
    $current = $this->setUpCurrentUser($this->accountValues(), ['administer users']);
    $hooks = $this->container->get(UserCancelHooks::class);
    $form = [];

    $form_state = (new FormState())->setValues([
      'uid' => $current->id(),
      'user_cancel_method' => UserCancelHooks::METHOD,
    ]);
    $hooks->validateCancelMethod($form, $form_state);
    $this->assertArrayHasKey('user_cancel_method', $form_state->getErrors());

    $account = $this->createAccount();
    $form_state = (new FormState())->setValues([
      'uid' => $account->id(),
      'user_cancel_method' => UserCancelHooks::METHOD,
      'user_cancel_confirm' => 1,
    ]);
    $hooks->validateCancelMethod($form, $form_state);
    $this->assertArrayHasKey('user_cancel_confirm', $form_state->getErrors());
  }

  /**
   * Creates a user with an email address.
   *
   * The username is generated from the email address on presave.
   *
   * @see hel_tpm_general_user_presave()
   */
  protected function createAccount(array $permissions = []): UserInterface {
    return $this->createUser($permissions, NULL, FALSE, $this->accountValues());
  }

  /**
   * Gets values for a new user with a unique email address.
   */
  protected function accountValues(): array {
    return ['mail' => $this->randomMachineName() . '@example.com'];
  }

  /**
   * Creates a node owned by the given user.
   */
  protected function createNodeFor(int $uid): Node {
    $node = Node::create([
      'type' => 'page',
      'title' => $this->randomString(),
      'uid' => $uid,
    ]);
    $node->save();
    return $node;
  }

}
