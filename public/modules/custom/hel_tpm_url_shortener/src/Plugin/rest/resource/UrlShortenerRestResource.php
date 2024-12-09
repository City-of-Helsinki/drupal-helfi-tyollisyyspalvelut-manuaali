<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_url_shortener\Plugin\rest\resource;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\hel_tpm_url_shortener\ShortUrlService;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Represents Url shortener rest records as resources.
 *
 * @RestResource (
 *   id = "hel_tpm_url_shortener_url_shortener_rest",
 *   label = @Translation("Url shortener rest"),
 *   uri_paths = {
 *     "create" = "/api/v1/shortenurl"
 *   }
 * )
 *
 * @DCG
 * The plugin exposes key-value records as REST resources. In order to enable it
 * import the resource configuration into active configuration storage. An
 * example of such configuration can be located in the following file:
 * core/modules/rest/config/optional/rest.resource.entity.node.yml.
 * Alternatively, you can enable it through admin interface provider by REST UI
 * module.
 * @see https://www.drupal.org/project/restui
 *
 * @DCG
 * Notice that this plugin does not provide any validation for the data.
 * Consider creating custom normalizer to validate and normalize the incoming
 * data. It can be enabled in the plugin definition as follows.
 * @code
 *   serialization_class = "Drupal\foo\MyDataStructure",
 * @endcode
 *
 * @DCG
 * For entities, it is recommended to use REST resource plugin provided by
 * Drupal core.
 * @see \Drupal\rest\Plugin\rest\resource\EntityResource
 */
final class UrlShortenerRestResource extends ResourceBase {

  /**
   * The key-value storage.
   */
  private readonly KeyValueStoreInterface $storage;

  /**
   * Short url service.
   *
   * @var \Drupal\hel_tpm_url_shortener\ShortUrlService
   */
  private readonly ShortUrlService $shortUrlService;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    KeyValueFactoryInterface $keyValueFactory,
    ShortUrlService $shortUrlService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->storage = $keyValueFactory->get('hel_tpm_url_shortener_url_shortener_rest');
    $this->shortUrlService = $shortUrlService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('keyvalue'),
      $container->get('hel_tpm_url_shortener.short_url_service')
    );
  }

  /**
   * Responds to POST requests and saves the new record.
   */
  public function post(array $data): ModifiedResourceResponse {
    if (empty($data['redirect_path'])) {
      return new ModifiedResourceResponse(NULL, 404);
    }
    $redirect_path = $data['redirect_path'];
    $short_link = $this->shortUrlService->generateShortLink($redirect_path);
    if (empty($short_link)) {
      return new ModifiedResourceResponse(NULL, 404);
    }
    $data['short_link'] = $short_link->getShortUrl();
    $this->storage->set($data['short_link'], $data);
    // Return the newly created record in the response body.
    return new ModifiedResourceResponse($data, 201);
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseRoute($canonical_path, $method): Route {
    $route = parent::getBaseRoute($canonical_path, $method);
    // Set ID validation pattern.
    if ($method !== 'POST') {
      $route->setRequirement('id', '\d+');
    }
    return $route;
  }

}
