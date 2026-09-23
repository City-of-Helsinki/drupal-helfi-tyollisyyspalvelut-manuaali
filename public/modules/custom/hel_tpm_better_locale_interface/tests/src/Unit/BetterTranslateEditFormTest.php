<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_better_locale_interface\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\hel_tpm_better_locale_interface\Form\BetterTranslateEditForm;
use Drupal\hel_tpm_better_locale_interface\Form\BetterTranslateFilterForm;
use Drupal\locale\Form\TranslateFormBase;
use Drupal\locale\LocaleConfigManager;
use Drupal\locale\PluralFormulaInterface;
use Drupal\locale\SourceString;
use Drupal\locale\StringStorageInterface;
use Drupal\locale\TranslationString;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the enhanced translation edit and filter forms.
 */
#[Group('hel_tpm_better_locale_interface')]
class BetterTranslateEditFormTest extends UnitTestCase {

  /**
   * The mocked locale storage.
   *
   * @var \Drupal\locale\StringStorageInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $localeStorage;

  /**
   * The mocked state service.
   *
   * @var \Drupal\Core\State\StateInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $state;

  /**
   * The mocked language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The mocked plural formula service.
   *
   * @var \Drupal\locale\PluralFormulaInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $pluralFormula;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    require_once $this->root . '/core/modules/locale/locale.module';

    $this->resetFilterValues();

    $this->localeStorage = $this->createMock(StringStorageInterface::class);
    $this->state = $this->createMock(StateInterface::class);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->pluralFormula = $this->createMock(PluralFormulaInterface::class);

    $this->setUpLanguages();

    $this->pluralFormula->method('getNumberOfPlurals')
      ->willReturn(2);

    $container = new ContainerBuilder();
    $container->set('locale.storage', $this->localeStorage);
    $container->set('state', $this->state);
    $container->set('language_manager', $this->languageManager);
    $container->set('locale.plural.formula', $this->pluralFormula);
    $container->set('string_translation', $this->getStringTranslationStub());
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger = $this->createMock(LoggerInterface::class);
    $logger_factory->method('get')->willReturn($logger);
    $container->set('logger.factory', $logger_factory);
    $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));
    $container->set('locale.config_manager', $this->createMock(LocaleConfigManager::class));

    $this->requestStack = new RequestStack();
    $request = new Request();
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->requestStack->push($request);
    $container->set('request_stack', $this->requestStack);

    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->resetFilterValues();
    parent::tearDown();
  }

  /**
   * Clears the filter cache shared by Drupal's translation forms.
   */
  protected function resetFilterValues(): void {
    $property = new \ReflectionProperty(TranslateFormBase::class, 'filterValues');
    $property->setValue(NULL, NULL);
  }

  /**
   * Configures the available translation languages.
   */
  protected function setUpLanguages(): void {
    $lang_fi = $this->createMock(LanguageInterface::class);
    $lang_fi->method('getId')->willReturn('fi');
    $lang_fi->method('getName')->willReturn('Finnish');

    $lang_sv = $this->createMock(LanguageInterface::class);
    $lang_sv->method('getId')->willReturn('sv');
    $lang_sv->method('getName')->willReturn('Swedish');

    $this->languageManager->method('getLanguages')
      ->willReturn(['fi' => $lang_fi, 'sv' => $lang_sv]);

    $current_lang = $this->createMock(LanguageInterface::class);
    $current_lang->method('getId')->willReturn('fi');
    $this->languageManager->method('getCurrentLanguage')
      ->willReturn($current_lang);
  }

  /**
   * Tests filter form options and default values.
   */
  public function testFilterFormAllLanguagesOption(): void {
    $filter_form = new BetterTranslateFilterForm($this->localeStorage, $this->state, $this->languageManager);
    $form_state = new FormState();
    $form = $filter_form->buildForm([], $form_state);

    $this->assertArrayHasKey('langcode', $form['filters']['status']);
    $this->assertEquals('all', $form['filters']['status']['langcode']['#default_value']);
    $this->assertArrayHasKey('all', $form['filters']['status']['langcode']['#options']);
    $this->assertArrayHasKey('fi', $form['filters']['status']['langcode']['#options']);
    $this->assertArrayHasKey('sv', $form['filters']['status']['langcode']['#options']);
  }

  /**
   * Tests edit form rendering all languages when language filter is 'all'.
   */
  public function testBuildFormRendersAllLanguages(): void {
    $source_string = new SourceString(['lid' => 1, 'source' => 'Hello', 'context' => '']);

    $this->localeStorage->method('getStrings')
      ->willReturn([$source_string]);

    $fi_trans = new TranslationString(['lid' => 1, 'language' => 'fi', 'translation' => 'Hei']);
    $sv_trans = new TranslationString(['lid' => 1, 'language' => 'sv', 'translation' => 'Hej']);

    $this->localeStorage->method('getTranslations')
      ->willReturn([$fi_trans, $sv_trans]);

    $this->requestStack->getCurrentRequest()->query->set('langcode', 'all');

    $edit_form = new BetterTranslateEditForm($this->localeStorage, $this->state, $this->languageManager);
    $form_state = new FormState();
    $form = $edit_form->buildForm([], $form_state);

    $this->assertArrayHasKey('strings', $form);
    $this->assertCount(3, $form['strings']['#header']);
    $this->assertEquals('Source string', (string) $form['strings']['#header'][0]);
    $this->assertEquals('Translation for Finnish', (string) $form['strings']['#header'][1]);
    $this->assertEquals('Translation for Swedish', (string) $form['strings']['#header'][2]);

    $this->assertArrayHasKey(1, $form['strings']);
    $this->assertArrayHasKey('original', $form['strings'][1]);
    $this->assertArrayHasKey('fi', $form['strings'][1]);
    $this->assertArrayHasKey('sv', $form['strings'][1]);
    $this->assertEquals('Hei', $form['strings'][1]['fi'][0]['#default_value']);
    $this->assertEquals('Hej', $form['strings'][1]['sv'][0]['#default_value']);
  }

  /**
   * Tests edit form rendering single language when filtered by language.
   */
  public function testBuildFormRendersSingleLanguage(): void {
    $fi_trans1 = new TranslationString([
      'lid' => 10,
      'source' => 'Installed %module module.',
      'language' => 'fi',
      'translation' => 'Asennettu moduuli %module.',
    ]);
    $fi_trans2 = new TranslationString([
      'lid' => 20,
      'source' => 'Updating translations.',
      'language' => 'fi',
      'translation' => 'Päivitetään käännöksiä.',
    ]);

    $this->localeStorage->method('getTranslations')
      ->willReturn([$fi_trans1, $fi_trans2]);

    $this->requestStack->getCurrentRequest()->query->set('langcode', 'fi');

    $edit_form = new BetterTranslateEditForm($this->localeStorage, $this->state, $this->languageManager);
    $form_state = new FormState();
    $form = $edit_form->buildForm([], $form_state);

    $this->assertArrayHasKey('strings', $form);
    $this->assertCount(2, $form['strings']['#header']);
    $this->assertEquals('Source string', (string) $form['strings']['#header'][0]);
    $this->assertEquals('Translation for Finnish', (string) $form['strings']['#header'][1]);

    $this->assertArrayHasKey(10, $form['strings']);
    $this->assertArrayHasKey('fi', $form['strings'][10]);
    $this->assertArrayNotHasKey('sv', $form['strings'][10]);
    $this->assertEquals('Asennettu moduuli %module.', $form['strings'][10]['fi'][0]['#default_value']);

    $this->assertArrayHasKey(20, $form['strings']);
    $this->assertArrayHasKey('fi', $form['strings'][20]);
    $this->assertArrayNotHasKey('sv', $form['strings'][20]);
    $this->assertEquals('Päivitetään käännöksiä.', $form['strings'][20]['fi'][0]['#default_value']);
  }

  /**
   * Tests edit form validation.
   */
  public function testValidateForm(): void {
    $edit_form = new BetterTranslateEditForm($this->localeStorage, $this->state, $this->languageManager);

    $form = [];
    $form_state = new FormState();
    $form_state->setValue('langcode', 'all');
    $form_state->setValue('strings', [
      1 => [
        'fi' => [0 => 'Hei'],
        'sv' => [0 => 'Hej <script>alert("xss")</script>'],
      ],
    ]);

    $edit_form->validateForm($form, $form_state);
    $this->assertNotEmpty($form_state->getErrors());
    $this->assertArrayHasKey('strings][1][sv][0', $form_state->getErrors());
  }

  /**
   * Tests edit form submit for all languages.
   */
  public function testSubmitFormAllLanguages(): void {
    $messenger = $this->createMock(MessengerInterface::class);
    \Drupal::getContainer()->set('messenger', $messenger);

    $fi_trans = new TranslationString(['lid' => 1, 'language' => 'fi', 'translation' => 'Hei']);
    $this->localeStorage->method('getTranslations')
      ->willReturn([$fi_trans]);

    $created_sv_trans = $this->createMock(TranslationString::class);
    $created_sv_trans->method('setPlurals')->willReturnSelf();
    $created_sv_trans->method('setCustomized')->willReturnSelf();
    $created_sv_trans->method('getId')->willReturn(10);
    $created_sv_trans->expects($this->once())->method('save');

    $this->localeStorage->expects($this->once())
      ->method('createTranslation')
      ->with(['lid' => 1, 'language' => 'sv'])
      ->willReturn($created_sv_trans);

    $edit_form = new BetterTranslateEditForm($this->localeStorage, $this->state, $this->languageManager);

    $form = [];
    $form_state = new FormState();
    $form_state->setValue('langcode', 'all');
    $form_state->setValue('strings', [
      1 => [
        'fi' => [0 => 'Hei'],
        'sv' => [0 => 'Hej'],
      ],
    ]);

    $edit_form->submitForm($form, $form_state);
  }

}
