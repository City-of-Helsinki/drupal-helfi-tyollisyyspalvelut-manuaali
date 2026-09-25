<?php

namespace Drupal\hel_tpm_url_shortener\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\hel_tpm_url_shortener\ShortUrlService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class responsible for handling AJAX requests for URL shortening.
 */
class ShortUrlAjaxController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Short url service.
   *
   * @var \Drupal\hel_tpm_url_shortener\ShortUrlService
   */
  protected $shortUrlService;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $request;

  /**
   * Constructor method.
   *
   * @param \Drupal\hel_tpm_url_shortener\ShortUrlService $short_url_service
   *   Service for handling short URL operations.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Stack of requests to manage the current HTTP request.
   *
   * @return void
   *   Void.
   */
  public function __construct(ShortUrlService $short_url_service, RequestStack $request_stack) {
    $this->shortUrlService = $short_url_service;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('hel_tpm_url_shortener.short_url_service'),
      $container->get('request_stack')
    );
  }

  /**
   * Generates a shortened URL based on the current path.
   *
   * @param mixed $param
   *   An optional parameter that is not used in the method.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JsonResponse object containing the generated short URL
   *   or an empty array if the current path is not provided.
   */
  public function createShortUrl($param = NULL) {
    $current = $this->request->query->get('current_path');
    if (empty($current)) {
      return new JsonResponse([]);
    }
    $short = $this->shortUrlService->generateShortLink($current);
    return new JsonResponse(['data' => $short->getShortUrl()]);
  }

}
