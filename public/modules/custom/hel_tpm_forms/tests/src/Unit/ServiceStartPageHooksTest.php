<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_forms\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Drupal\hel_tpm_forms\Hook\ServiceStartPageHooks;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the service creation start page logic.
 *
 * @coversDefaultClass \Drupal\hel_tpm_forms\Hook\ServiceStartPageHooks
 * @group hel_tpm_forms
 */
class ServiceStartPageHooksTest extends UnitTestCase {

  /**
   * URL of the service creation form.
   */
  protected const string FORM_URL = 'https://example.com/node/add/service';

  /**
   * Tests when the start page is shown.
   *
   * @covers ::isStartPage
   */
  public function testIsStartPage(): void {
    $hooks = $this->createHooks();

    $new_entity = $this->createEntity(TRUE);
    $existing_entity = $this->createEntity(FALSE);

    $this->assertTrue($hooks->isStartPage($new_entity, new FormState()));
    $this->assertFalse($hooks->isStartPage($existing_entity, new FormState()));

    $submitted = (new FormState())->setUserInput(['langcode' => [['value' => 'sv']]]);
    $this->assertFalse($hooks->isStartPage($new_entity, $submitted));

    $rebuilding = (new FormState())->setRebuild();
    $this->assertFalse($hooks->isStartPage($new_entity, $rebuilding));

    // The step navigation adds the step to the URL also on the start page.
    $at_step = $this->createHooks(NULL, self::FORM_URL . '?step=0');
    $this->assertTrue($at_step->isStartPage($new_entity, new FormState()));

    $language_selected = $this->createHooks(NULL, self::FORM_URL . '?step=0&language_selected=1');
    $this->assertFalse($language_selected->isStartPage($new_entity, new FormState()));
  }

  /**
   * Tests the selected language.
   *
   * @covers ::getSelectedLangcode
   */
  public function testGetSelectedLangcode(): void {
    $hooks = $this->createHooks();
    $new_entity = $this->createEntity(TRUE);
    $existing_entity = $this->createEntity(FALSE);

    // A new service gets the language of the page.
    $this->assertSame('en', $hooks->getSelectedLangcode($new_entity, new FormState()));
    // An existing service keeps its language.
    $this->assertSame('fi', $hooks->getSelectedLangcode($existing_entity, new FormState()));

    $submitted = (new FormState())->setUserInput(['langcode' => [['value' => 'sv']]]);
    $this->assertSame('sv', $hooks->getSelectedLangcode($new_entity, $submitted));

    $unknown = (new FormState())->setUserInput(['langcode' => [['value' => 'xx']]]);
    $this->assertSame('en', $hooks->getSelectedLangcode($new_entity, $unknown));
  }

  /**
   * Tests that a local referring page is used as the exit URL.
   *
   * @covers ::getExitUrl
   */
  public function testExitUrlLocalReferer(): void {
    $url = $this->createHooks('https://example.com/group/1/nodes')->getExitUrl(new FormState());
    $this->assertSame('https://example.com/group/1/nodes', $url->getUri());
  }

  /**
   * Tests that the exit URL is kept over a language change of the form.
   *
   * @covers ::getReturnUrl
   * @covers ::getExitUrl
   */
  public function testExitUrlKeptOverLanguageChange(): void {
    $return = 'https://example.com/group/1/nodes';
    $hooks = $this->createHooks('https://example.com/fi/node/add/service', self::FORM_URL . '?step=0&return=' . urlencode($return));
    $this->assertSame($return, $hooks->getReturnUrl());
    $this->assertSame($return, $hooks->getExitUrl(new FormState())->getUri());
  }

  /**
   * Tests that return URLs to other sites or malformed URLs are not used.
   *
   * @covers ::getReturnUrl
   * @dataProvider providerExternalReturnUrl
   */
  public function testExternalReturnUrl(string $url): void {
    $this->assertNull($this->createHooks($url)->getReturnUrl());
    $this->assertNull($this->createHooks(NULL, self::FORM_URL . '?return=' . urlencode($url))->getReturnUrl());
  }

  /**
   * Data provider for testExternalReturnUrl().
   *
   * @return array
   *   URLs of other sites and malformed URLs.
   */
  public static function providerExternalReturnUrl(): array {
    return [
      'other host' => ['https://evil.example.org/'],
      'protocol relative' => ['//evil.example.org/'],
      'host as subdomain' => ['https://example.com.evil.example.org/'],
      'javascript' => ['javascript:alert(1)'],
      'without host' => ['https:///node/1'],
    ];
  }

  /**
   * Tests the exit URL fallbacks.
   *
   * @covers ::getExitUrl
   * @dataProvider providerExitUrlFallback
   */
  public function testExitUrlFallback(?string $referer): void {
    $hooks = $this->createHooks($referer);

    $url = $hooks->getExitUrl(new FormState());
    $this->assertSame('<front>', $url->getRouteName());

    $group_url = Url::fromRoute('entity.group.canonical', ['group' => 1]);
    $group = $this->createMock(EntityInterface::class);
    $group->method('toUrl')->willReturn($group_url);
    $url = $hooks->getExitUrl((new FormState())->set('group', $group));
    $this->assertSame($group_url, $url);
  }

  /**
   * Data provider for testExitUrlFallback().
   *
   * @return array
   *   Referer header values that must not be used as the exit URL.
   */
  public static function providerExitUrlFallback(): array {
    return [
      'no referer' => [NULL],
      'external referer' => ['https://evil.example.org/'],
      'form itself' => [self::FORM_URL],
    ];
  }

  /**
   * Creates a Finnish entity.
   *
   * @param bool $is_new
   *   Whether the entity is new.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  protected function createEntity(bool $is_new): EntityInterface {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('isNew')->willReturn($is_new);
    $entity->method('language')->willReturn(new Language(['id' => 'fi', 'name' => 'Finnish']));
    return $entity;
  }

  /**
   * Creates the hook class.
   *
   * The current language of the page is English and Swedish is also known.
   *
   * @param string|null $referer
   *   The referer header value.
   * @param string $uri
   *   The URI of the request.
   *
   * @return \Drupal\hel_tpm_forms\Hook\ServiceStartPageHooks
   *   The hook class.
   */
  protected function createHooks(?string $referer = NULL, string $uri = self::FORM_URL): ServiceStartPageHooks {
    $request = Request::create($uri);
    if ($referer !== NULL) {
      $request->headers->set('referer', $referer);
    }
    $request_stack = new RequestStack();
    $request_stack->push($request);

    $languages = [
      'en' => new Language(['id' => 'en', 'name' => 'English']),
      'sv' => new Language(['id' => 'sv', 'name' => 'Swedish']),
    ];
    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager->method('getCurrentLanguage')->willReturn($languages['en']);
    $language_manager->method('getLanguage')->willReturnCallback(
      fn (string $langcode) => $languages[$langcode] ?? NULL,
    );

    $hooks = new ServiceStartPageHooks(
      $request_stack,
      $this->createMock(RouteMatchInterface::class),
      $language_manager,
    );
    $hooks->setStringTranslation($this->getStringTranslationStub());
    return $hooks;
  }

}
