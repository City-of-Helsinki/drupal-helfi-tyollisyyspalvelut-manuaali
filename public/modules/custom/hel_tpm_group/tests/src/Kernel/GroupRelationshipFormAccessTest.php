<?php

namespace Drupal\Tests\hel_tpm_group\Kernel;

use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Url;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\group\Traits\NodeTypeCreationTrait;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\PermissionScopeInterface;
use Drupal\user\RoleInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests added permission requirements for group content forms.
 *
 * @see \Drupal\hel_tpm_group\EventSubscriber\HelTpmGroupRouteSubscriber::alterRoutes()
 *
 * @coversDefaultClass \Drupal\hel_tpm_group\EventSubscriber\HelTpmGroupRouteSubscriber
 *
 * @group hel_tpm_group
 */
class GroupRelationshipFormAccessTest extends GroupKernelTestBase {

  use NodeTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'hel_tpm_group',
    'group',
    'ggroup',
    'message',
    'message_notify',
    'group_test_plugin',
    'node',
  ];

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The group type.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupType;

  /**
   * The group admin role.
   *
   * @var \Drupal\group\Entity\GroupRoleInterface
   */
  protected $adminRole;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('entity_test_with_owner');
    $this->createNodeType(['type' => 'service_test']);

    $this->accessManager = $this->container->get('access_manager');
    $this->routeProvider = $this->container->get('router.route_provider');
    $this->groupType = $this->createGroupType([
      'id' => 'form_access_test',
      'creator_membership' => FALSE,
    ]);

    $storage = $this->entityTypeManager->getStorage('group_content_type');
    $storage->save($storage->createFromPlugin($this->groupType, 'entity_test_as_content'));

    $this->adminRole = $this->createGroupRole([
      'group_type' => $this->groupType->id(),
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'admin' => TRUE,
    ]);
  }

  /**
   * Tests outsider's access to the add form.
   *
   * @param string[] $userPermissions
   *   User's permissions.
   * @param bool $shouldAccess
   *   Whether the user should have access or not.
   *
   * @return void
   *   -
   *
   * @throws \Exception
   *
   * @dataProvider outsiderFormAccessProvider
   */
  public function testOutsiderFormAccess(array $userPermissions, bool $shouldAccess) {
    $outsider = $this->createUser(
      $userPermissions
    );
    $this->createGroupRole([
      'group_type' => $this->groupType->id(),
      'scope' => PermissionScopeInterface::OUTSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
    ]);
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $request = $this->createRequest('entity.group_content.add_form', $group);

    $this->assertSame($shouldAccess, $this->accessManager->checkRequest($request, $outsider));
  }

  /**
   * Data provider for testOutsiderFormAccess().
   *
   * @return array
   *   Provided data.
   */
  public function outsiderFormAccessProvider(): array {
    return [
      'no-permissions' => [
        [],
        FALSE,
      ],
      'user-admin-permission' => [
        ['administer users'],
        FALSE,
      ],
    ];
  }

  /**
   * Tests group member's access to the add form.
   *
   * @param string[] $userPermissions
   *   User's permissions.
   * @param array $groupPermissions
   *   Member's group permissions.
   * @param bool $shouldAccess
   *   Whether the user should have access or not.
   *
   * @return void
   *   -
   *
   * @throws \Exception
   *
   * @dataProvider memberFormAccessProvider
   */
  public function testMemberFormAccess(array $userPermissions, array $groupPermissions, bool $shouldAccess) {
    $member = $this->createUser(
      $userPermissions
    );
    $this->createGroupRole([
      'group_type' => $this->groupType->id(),
      'scope' => PermissionScopeInterface::INSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'permissions' => $groupPermissions,
    ]);
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $group->addMember($member);
    $request = $this->createRequest('entity.group_content.add_form', $group);

    $this->assertSame($shouldAccess, $this->accessManager->checkRequest($request, $member));
  }

  /**
   * Data provider for testMemberFormAccess().
   *
   * @return array
   *   Provided data.
   */
  public function memberFormAccessProvider(): array {
    return [
      'no-permissions' => [
        [],
        [],
        FALSE,
      ],
      'create-permission' => [
        [],
        ['create entity_test_as_content relationship'],
        FALSE,
      ],
      'create-and-user-admin-permissions' => [
        ['administer users'],
        ['create entity_test_as_content relationship'],
        TRUE,
      ],
    ];
  }

  /**
   * Tests group admin's access to the add form.
   *
   * @param string[] $userPermissions
   *   User's permissions.
   * @param bool $shouldAccess
   *   Whether the user should have access or not.
   *
   * @return void
   *   -
   *
   * @throws \Exception
   *
   * @dataProvider groupAdminFormAccessProvider
   */
  public function testGroupAdminFormAccess(array $userPermissions, bool $shouldAccess) {
    $admin = $this->createUser(
      $userPermissions
    );
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $group->addMember($admin, ['group_roles' => [$this->adminRole->id()]]);
    $request = $this->createRequest('entity.group_content.add_form', $group);

    $this->assertSame($shouldAccess, $this->accessManager->checkRequest($request, $admin));
  }

  /**
   * Data provider for testGroupAdminFormAccess().
   *
   * @return array
   *   Provided data.
   */
  public function groupAdminFormAccessProvider(): array {
    return [
      'no-permissions' => [
        [],
        FALSE,
      ],
      'user-admin-permission' => [
        ['administer users'],
        TRUE,
      ],
    ];
  }

  /**
   * Creates a request for route.
   *
   * @param string $route
   *   The route name.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   *
   * @throws \Exception
   */
  protected function createRequest(string $route, GroupInterface $group): Request {
    $params = [
      'group' => $group->id(),
      'plugin_id' => 'entity_test_as_content',
    ];
    $attributes = [
      'group' => $group,
      'plugin_id' => 'entity_test_as_content',
    ];
    $attributes[RouteObjectInterface::ROUTE_NAME] = $route;
    $attributes[RouteObjectInterface::ROUTE_OBJECT] = $this->routeProvider->getRouteByName($route);
    $attributes['_raw_variables'] = new ParameterBag($params);
    $request = Request::create(Url::fromRoute($route, $params)->toString());
    $request->attributes->add($attributes);
    return $request;
  }

}
