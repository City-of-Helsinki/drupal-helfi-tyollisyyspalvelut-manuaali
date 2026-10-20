<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_forms\Hook;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Start page shown before the multistep service creation form.
 *
 * The start page is the "group_service_start" field group of the service form
 * display, so fields are added to the start page by placing them into that
 * group in Field UI. When a new service is being created, the group is shown
 * alone as the start page until the user continues to the form. The start page
 * can be opened again from the first step of the form.
 *
 * The language is selectable only on the start page. A new service gets the
 * language of the page by default. When another language is selected, the
 * form is loaded again in the selected language, so that the whole form is in
 * the language of the service. On all service forms the selected language is
 * shown read-only with the other meta information.
 *
 * @see js/hel_tpm_forms_service_start.js
 */
class ServiceStartPageHooks {

  use StringTranslationTrait;

  /**
   * Field group holding the start page fields.
   */
  public const string GROUP = 'group_service_start';

  /**
   * Query parameter of the current step of the service form.
   *
   * The step is kept in the URL by the form pagination JS.
   */
  public const string STEP_PARAMETER = 'step';

  /**
   * Query parameter telling that the language has been selected.
   *
   * Added to the URL by the start page when the user continues from it. A form
   * opened with it continues without the start page.
   */
  public const string LANGUAGE_SELECTED_PARAMETER = 'language_selected';

  /**
   * Query parameter keeping the page the user came from over a language change.
   *
   * Used as the URL of the exit link of the start page.
   */
  public const string RETURN_PARAMETER = 'return';

  public function __construct(
    protected readonly RequestStack $requestStack,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Implements hook_field_group_form_process_build_alter().
   */
  #[Hook('field_group_form_process_build_alter')]
  public function fieldGroupFormProcessBuildAlter(array &$element, ?FormStateInterface $form_state = NULL, array &$form = []): void {
    if (empty($element['#fieldgroups'][self::GROUP]) || $form_state === NULL) {
      return;
    }
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof ContentEntityFormInterface) {
      return;
    }
    $entity = $form_object->getEntity();
    if ($entity->getEntityTypeId() !== 'node' || $entity->bundle() !== 'service') {
      return;
    }

    $langcode = $this->getSelectedLangcode($entity, $form_state);

    // Show the selected language read-only with the other meta information.
    if (isset($element['meta'])) {
      $element['meta']['service_language'] = [
        '#type' => 'item',
        '#title' => $this->t('Language version:'),
        '#weight' => -10,
        '#wrapper_attributes' => ['class' => ['entity-meta__language']],
        '#plain_text' => ($this->languageManager->getLanguage($langcode) ?? $entity->language())->getName(),
      ];
    }

    // The start page fields, such as the language and its info box, are used
    // only when creating a service.
    if (!$entity->isNew()) {
      $element['langcode']['#access'] = FALSE;
      $element[self::GROUP]['#access'] = FALSE;
      return;
    }

    if (isset($element['langcode']['widget'][0]['value'])) {
      $element['langcode']['widget'][0]['value']['#default_value'] = $langcode;
      $element['langcode']['widget'][0]['value']['#title'] = $this->t('In what language do you want to add the new service?');
    }
    $element['#attached']['library'][] = 'hel_tpm_forms/service_start';
    if ($this->isStartPage($entity, $form_state)) {
      // Render the start page alone at the top of the form already on the
      // server, so that the rest of the form isn't shown while loading.
      $element['#attributes']['data-service-start'] = 'start';
      $element['#attributes']['class'][] = 'service-start-active';
      unset($element[self::GROUP]['#group']);
    }
    else {
      // The user has continued past the start page. The start page fields
      // stay in the form so that their values are submitted, and the start
      // page can be opened again from the first step.
      $element['#attributes']['data-service-start'] = 'continue';
      $attributes = $element[self::GROUP]['#attributes'] ?? [];
      if ($attributes instanceof Attribute) {
        $attributes->addClass('service-start-hidden');
      }
      else {
        $element[self::GROUP]['#attributes']['class'][] = 'service-start-hidden';
      }
    }

    // Button for returning to the start page from the first step.
    if (isset($element['paging_header'])) {
      $element['paging_header']['#start_page'] = TRUE;
    }

    // The start page depends on the query parameters and the exit link also
    // on the referring page.
    $cacheability = CacheableMetadata::createFromRenderArray($element)
      ->addCacheContexts(['headers:referer', 'url.query_args']);
    $element['#attached']['drupalSettings']['hel_tpm_forms']['service_start'] = [
      'languageSelectedParameter' => self::LANGUAGE_SELECTED_PARAMETER,
      'currentLanguage' => $this->languageManager->getCurrentLanguage()->getId(),
      'languageUrls' => $this->getLanguageUrls($cacheability),
    ];
    $exit_url = $this->getExitUrl($form_state)->toString(TRUE);
    $cacheability->addCacheableDependency($exit_url);
    $cacheability->applyTo($element);

    $element[self::GROUP]['service_start_actions'] = [
      '#theme' => 'hel_tpm_service_start_actions',
      '#exit_url' => $exit_url->getGeneratedUrl(),
      '#weight' => 1000,
    ];
  }

  /**
   * Checks whether the start page should be shown.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   TRUE on the initial build of a node creation form when the language has
   *   not been selected yet.
   */
  public function isStartPage(EntityInterface $entity, FormStateInterface $form_state): bool {
    $request = $this->requestStack->getCurrentRequest();
    return $entity->isNew()
      && !$form_state->isRebuilding()
      && empty($form_state->getUserInput())
      && !$request?->query->has(self::LANGUAGE_SELECTED_PARAMETER);
  }

  /**
   * Gets the code of the language selected for the service.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The submitted language when the form has been submitted. Otherwise the
   *   language of the page for a new entity and the language of the entity
   *   for an existing entity.
   */
  public function getSelectedLangcode(EntityInterface $entity, FormStateInterface $form_state): string {
    // The user input isn't set before the form is built.
    $input = $form_state->getUserInput() ?? [];
    $langcode = NestedArray::getValue($input, ['langcode', 0, 'value']);
    if (is_string($langcode) && $this->languageManager->getLanguage($langcode)) {
      return $langcode;
    }
    if ($entity->isNew()) {
      return $this->languageManager->getCurrentLanguage()->getId();
    }
    return $entity->language()->getId();
  }

  /**
   * Gets the URLs of the current form in each language.
   *
   * The URLs open the form at its first step without the start page.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cacheability metadata of the generated URLs is added to this.
   *
   * @return string[]
   *   The URLs keyed by language code.
   */
  public function getLanguageUrls(CacheableMetadata $cacheability): array {
    $request = $this->requestStack->getCurrentRequest();
    $query = $request ? $request->query->all() : [];
    $query[self::STEP_PARAMETER] = 0;
    $query[self::LANGUAGE_SELECTED_PARAMETER] = 1;
    // Keep the page the user came from as the exit URL in the new language.
    unset($query[self::RETURN_PARAMETER]);
    if ($return_url = $this->getReturnUrl()) {
      $query[self::RETURN_PARAMETER] = $return_url;
    }

    $urls = [];
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      $generated_url = Url::fromRouteMatch($this->routeMatch)
        ->setOption('language', $language)
        ->setOption('query', $query)
        ->toString(TRUE);
      $cacheability->addCacheableDependency($generated_url);
      $urls[$langcode] = $generated_url->getGeneratedUrl();
    }
    return $urls;
  }

  /**
   * Gets the page the user came to the form from.
   *
   * @return string|null
   *   The absolute URL kept over a language change of the form, or else the
   *   referring page. NULL when it is not a local page other than the form.
   */
  public function getReturnUrl(): ?string {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return NULL;
    }
    $base_url = $request->getSchemeAndHttpHost() . $request->getBasePath() . '/';
    $candidates = [
      $request->query->get(self::RETURN_PARAMETER),
      $request->headers->get('referer'),
    ];
    foreach ($candidates as $url) {
      if (!is_string($url) || $url === $request->getUri() || !UrlHelper::isExternal($url)) {
        continue;
      }
      try {
        if (UrlHelper::externalIsLocal($url, $base_url)) {
          return $url;
        }
      }
      catch (\InvalidArgumentException) {
        // The URL is malformed, e.g. without a host. Both candidates come
        // from the client, so they are not trusted.
      }
    }
    return NULL;
  }

  /**
   * Gets the URL of the exit link of the start page.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Url
   *   The page the user came from when it is a local page other than the form
   *   itself, otherwise the group the service is created in or the front page.
   */
  public function getExitUrl(FormStateInterface $form_state): Url {
    if ($return_url = $this->getReturnUrl()) {
      return Url::fromUri($return_url);
    }

    $group = $form_state->get('group');
    if ($group instanceof EntityInterface) {
      return $group->toUrl();
    }
    return Url::fromRoute('<front>');
  }

}
