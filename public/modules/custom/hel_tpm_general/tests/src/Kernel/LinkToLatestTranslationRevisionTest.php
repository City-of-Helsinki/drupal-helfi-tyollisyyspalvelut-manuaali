<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_general\Kernel;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\hel_tpm_general\Plugin\views\field\LinkToLatestTranslationRevision;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\ResultRow;

/**
 * Tests the link_to_latest_translation_revision Views field plugin.
 *
 * @group hel_tpm_general
 */
final class LinkToLatestTranslationRevisionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'language',
    'content_translation',
    'node',
    'views',
  ];

  /**
   * Tests that URL generation always uses the latest-version link template.
   */
  public function testGetUrlInfoUsesLatestVersionTemplate(): void {
    $url = Url::fromRoute('entity.node.latest_version', ['node' => 1]);
    $entity = $this->createEntityExpectingUrl('latest-version', $url);

    $plugin = $this->createPlugin();
    $this->setOptions($plugin, absolute: TRUE);

    $result = $this->invokeProtectedMethod($plugin, 'getUrlInfo', [
      $this->createRow($entity),
    ]);

    $this->assertSame($url, $result);
    $this->assertTrue($result->getOption('absolute'));
  }

  /**
   * Tests that multilingual URL generation adds language information.
   */
  public function testGetUrlInfoAddsLanguageWhenMultilingual(): void {
    $language = $this->createLanguage('fi');
    $url = Url::fromRoute('entity.node.latest_version', ['node' => 1]);

    $entity = $this->createEntityExpectingUrl('latest-version', $url);
    $entity
      ->method('language')
      ->willReturn($language);

    $plugin = $this->createPlugin(multilingual: TRUE);
    $this->setOptions($plugin);

    $result = $this->invokeProtectedMethod($plugin, 'getUrlInfo', [
      $this->createRow($entity),
    ]);
    $options = $this->getProtectedProperty($plugin, 'options');

    $this->assertSame($url, $result);
    $this->assertFalse($result->getOption('absolute'));
    $this->assertSame($language, $options['alter']['language']);
  }

  /**
   * Tests that access is checked directly on the entity.
   */
  public function testCheckUrlAccessChecksEditAccessOnEntity(): void {
    $account = $this->createMock(AccountInterface::class);
    $entity = $this->createEntityExpectingAccess($account, AccessResult::allowed());

    $plugin = $this->createPlugin();
    $this->setCurrentUser($plugin, $account);
    $this->setOptions($plugin);

    $result = $this->invokeProtectedMethod($plugin, 'checkUrlAccess', [
      $this->createRow($entity),
    ]);

    $this->assertInstanceOf(AccessResultInterface::class, $result);
    $this->assertTrue($result->isAllowed());
  }

  /**
   * Tests that multilingual access still checks edit access on the entity.
   */
  public function testCheckUrlAccessChecksEditAccessWhenMultilingual(): void {
    $account = $this->createMock(AccountInterface::class);
    $language = $this->createLanguage('fi');

    $entity = $this->createEntityExpectingAccess($account, AccessResult::allowed());
    $entity
      ->method('language')
      ->willReturn($language);

    $plugin = $this->createPlugin(multilingual: TRUE);
    $this->setCurrentUser($plugin, $account);
    $this->setOptions($plugin);

    $result = $this->invokeProtectedMethod($plugin, 'checkUrlAccess', [
      $this->createRow($entity),
    ]);

    $this->assertInstanceOf(AccessResultInterface::class, $result);
    $this->assertTrue($result->isAllowed());
  }

  /**
   * Tests that route access is not used for the latest revision link.
   */
  public function testCheckUrlAccessDoesNotUseNamedRouteAccess(): void {
    $account = $this->createMock(AccountInterface::class);
    $entity = $this->createEntityExpectingAccess($account, AccessResult::allowed());

    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager
      ->expects($this->never())
      ->method('checkNamedRoute');

    $plugin = $this->createPlugin(access_manager: $access_manager);
    $this->setCurrentUser($plugin, $account);
    $this->setOptions($plugin);

    $result = $this->invokeProtectedMethod($plugin, 'checkUrlAccess', [
      $this->createRow($entity),
    ]);

    $this->assertInstanceOf(AccessResultInterface::class, $result);
    $this->assertTrue($result->isAllowed());
  }

  /**
   * Tests render output for denied edit access results.
   *
   * @dataProvider deniedAccessResultsProvider
   */
  public function testRenderDoesNotOutputLinkWhenEditAccessIsDenied(AccessResultInterface $access_result): void {
    $account = $this->createMock(AccountInterface::class);
    $entity = $this->createEntityExpectingAccess($account, $access_result);
    $entity
      ->expects($this->never())
      ->method('toUrl');

    $plugin = $this->createPlugin();
    $this->setCurrentUser($plugin, $account);
    $this->setOptions($plugin, [
      'text' => 'View latest revision',
      'output_url_as_text' => FALSE,
    ]);

    $build = $plugin->render($this->createRow($entity));

    $this->assertIsArray($build);
    $this->assertSame('', $build['#markup']);
  }

  /**
   * Provides denied access results.
   *
   * @return array<string, array{\Drupal\Core\Access\AccessResultInterface}>
   *   Access result test cases.
   */
  public function deniedAccessResultsProvider(): array {
    return [
      'forbidden access' => [AccessResult::forbidden()],
      'neutral access' => [AccessResult::neutral()],
    ];
  }

  /**
   * Creates the plugin under test.
   *
   * @param bool $multilingual
   *   Whether the language manager should report multilingual support.
   * @param \Drupal\Core\Access\AccessManagerInterface|null $access_manager
   *   The access manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface|null $entity_repository
   *   The entity repository.
   *
   * @return \Drupal\hel_tpm_general\Plugin\views\field\LinkToLatestTranslationRevision
   *   The plugin instance.
   */
  private function createPlugin(
    bool $multilingual = FALSE,
    ?AccessManagerInterface $access_manager = NULL,
    ?EntityRepositoryInterface $entity_repository = NULL,
  ): LinkToLatestTranslationRevision {
    $access_manager ??= $this->createMock(AccessManagerInterface::class);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_repository ??= $this->createMock(EntityRepositoryInterface::class);

    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager
      ->method('isMultilingual')
      ->willReturn($multilingual);

    return new LinkToLatestTranslationRevision(
      [],
      'link_to_latest_translation_revision',
      [],
      $access_manager,
      $entity_type_manager,
      $entity_repository,
      $language_manager,
    );
  }

  /**
   * Creates a Views result row for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The row entity.
   *
   * @return \Drupal\views\ResultRow
   *   The result row.
   */
  private function createRow(EntityInterface $entity): ResultRow {
    $row = new ResultRow();
    $row->_entity = $entity;

    return $row;
  }

  /**
   * Creates a language mock.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   The language mock.
   */
  private function createLanguage(string $langcode): LanguageInterface {
    $language = $this->createMock(LanguageInterface::class);
    $language
      ->method('getId')
      ->willReturn($langcode);

    return $language;
  }

  /**
   * Creates an entity mock expecting URL generation.
   *
   * @param string $template
   *   The expected link template.
   * @param \Drupal\Core\Url $url
   *   The URL to return.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity mock.
   */
  private function createEntityExpectingUrl(string $template, Url $url): EntityInterface {
    $entity = $this->createMock(EntityInterface::class);
    $entity
      ->expects($this->once())
      ->method('toUrl')
      ->with($template)
      ->willReturn($url);

    return $entity;
  }

  /**
   * Creates an entity mock expecting an edit access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account expected in the access check.
   * @param \Drupal\Core\Access\AccessResultInterface $access_result
   *   The access result to return.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity mock.
   */
  private function createEntityExpectingAccess(AccountInterface $account, AccessResultInterface $access_result): EntityInterface {
    $entity = $this->createMock(EntityInterface::class);
    $entity
      ->expects($this->once())
      ->method('access')
      ->with('edit', $account, TRUE)
      ->willReturn($access_result);

    return $entity;
  }

  /**
   * Sets plugin options.
   *
   * @param \Drupal\hel_tpm_general\Plugin\views\field\LinkToLatestTranslationRevision $plugin
   *   The plugin.
   * @param array $extra_options
   *   Additional options.
   * @param bool $absolute
   *   Whether generated URLs should be absolute.
   */
  private function setOptions(LinkToLatestTranslationRevision $plugin, array $extra_options = [], bool $absolute = FALSE): void {
    $this->setProtectedProperty($plugin, 'options', $extra_options + [
      'relationship' => 'none',
      'absolute' => $absolute,
      'alter' => [],
    ]);
  }

  /**
   * Sets the current user on the plugin.
   *
   * @param \Drupal\hel_tpm_general\Plugin\views\field\LinkToLatestTranslationRevision $plugin
   *   The plugin.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   */
  private function setCurrentUser(LinkToLatestTranslationRevision $plugin, AccountInterface $account): void {
    $this->setProtectedProperty($plugin, 'currentUser', $account);
  }

  /**
   * Invokes a protected method.
   *
   * @param object $object
   *   The object.
   * @param string $method
   *   The method name.
   * @param array $arguments
   *   The method arguments.
   *
   * @return mixed
   *   The method return value.
   */
  private function invokeProtectedMethod(object $object, string $method, array $arguments = []): mixed {
    $reflection = new \ReflectionClass($object);
    $reflection_method = $reflection->getMethod($method);
    $reflection_method->setAccessible(TRUE);

    return $reflection_method->invokeArgs($object, $arguments);
  }

  /**
   * Sets a protected property value.
   *
   * @param object $object
   *   The object.
   * @param string $property
   *   The property name.
   * @param mixed $value
   *   The property value.
   */
  private function setProtectedProperty(object $object, string $property, mixed $value): void {
    $reflection = new \ReflectionClass($object);

    while (!$reflection->hasProperty($property)) {
      $reflection = $reflection->getParentClass();
    }

    $reflection_property = $reflection->getProperty($property);
    $reflection_property->setAccessible(TRUE);
    $reflection_property->setValue($object, $value);
  }

  /**
   * Gets a protected property value.
   *
   * @param object $object
   *   The object.
   * @param string $property
   *   The property name.
   *
   * @return mixed
   *   The property value.
   */
  private function getProtectedProperty(object $object, string $property): mixed {
    $reflection = new \ReflectionClass($object);

    while (!$reflection->hasProperty($property)) {
      $reflection = $reflection->getParentClass();
    }

    $reflection_property = $reflection->getProperty($property);
    $reflection_property->setAccessible(TRUE);

    return $reflection_property->getValue($object);
  }

}
