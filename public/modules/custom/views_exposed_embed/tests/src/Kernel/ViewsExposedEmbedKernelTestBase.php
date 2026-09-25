<?php

declare(strict_types=1);

namespace Drupal\Tests\views_exposed_embed\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\EntityTestHelper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Base class for the Views Exposed Embed kernel tests.
 *
 * Provides a test view (views_exposed_embed_test module) listing entity_test
 * entities, three bundles with test content and a views exposed embed field
 * on the 'host' bundle.
 */
abstract class ViewsExposedEmbedKernelTestBase extends KernelTestBase {

  use UserCreationTrait;

  /**
   * The test view ID.
   */
  protected const VIEW_ID = 'test_exposed_embed';

  /**
   * The test field name.
   */
  protected const FIELD_NAME = 'field_embed';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'entity_test',
    'views',
    'views_exposed_embed',
    'views_exposed_embed_test',
  ];

  /**
   * The field configuration.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected FieldConfig $field;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installConfig(['system', 'field', 'views', 'views_exposed_embed_test']);
    $this->container->get('router.builder')->rebuild();

    // Anonymous and the super user, so that test users are regular users.
    User::create(['uid' => 0, 'name' => ''])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();

    foreach (['foo', 'bar', 'host'] as $bundle) {
      EntityTestHelper::createBundle($bundle);
    }

    EntityTest::create(['type' => 'foo', 'name' => 'Foo item'])->save();
    EntityTest::create(['type' => 'bar', 'name' => 'Bar item'])->save();
    // Hidden by the non-exposed 'name_hidden' filter of the test view.
    EntityTest::create(['type' => 'foo', 'name' => 'Secret item'])->save();

    FieldStorageConfig::create([
      'field_name' => static::FIELD_NAME,
      'entity_type' => 'entity_test',
      'type' => 'views_exposed_embed_field',
    ])->save();
    $this->field = FieldConfig::create([
      'field_name' => static::FIELD_NAME,
      'entity_type' => 'entity_test',
      'bundle' => 'host',
      'settings' => [
        'view_id' => static::VIEW_ID,
        'display_id' => 'exposed_embed_1',
      ],
    ]);
    $this->field->save();
  }

  /**
   * Makes a GET request with the given query the current request.
   *
   * @param array $query
   *   The query parameters.
   */
  protected function setRequestQuery(array $query): void {
    $request = Request::create('/', 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
  }

  /**
   * Creates a host entity with the given embed field value.
   *
   * @param array|null $filters
   *   The preset filter values, keyed by exposed filter identifier.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The saved host entity.
   */
  protected function createHost(?array $filters = []): EntityInterface {
    $host = EntityTest::create([
      'type' => 'host',
      'name' => 'Host item',
      static::FIELD_NAME => ['value' => $filters],
    ]);
    $host->save();
    return $host;
  }

}
