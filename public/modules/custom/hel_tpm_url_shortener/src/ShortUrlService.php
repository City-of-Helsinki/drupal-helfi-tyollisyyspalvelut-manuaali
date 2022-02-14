<?php

namespace Drupal\hel_tpm_url_shortener;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * ShortUrlService service.
 */
class ShortUrlService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a ShortUrlService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * Generate short url
   *
   * @return null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function generateShortLink() {
    $request = $this->requestStack->getCurrentRequest();
    $request->query->remove('ajax_form');
    $request->query->remove('_wrapper_format');
    // @DCG place your code here.
    $route = Url::fromUserInput($request->getPathInfo())->getRouteName();
    if (empty($route)) {
      return NULL;
    }

    $params = [];
    $query_params = $request->query->all();
    if (!empty($query_params)) {
      $params = array_merge($params, $query_params);
    }

    if ($request->attributes->has('_entity')) {
      $entity = $request->attributes->get('_entity');
      $params[$entity->getEntityTypeId()] = $entity->id();
    }

    $url = Url::fromRoute($route, $params);

    if ($url->isExternal())
      return NULL;

    $hash = md5($url->toString());
    $shortlink = $this->shortLinkExists($hash);
    if (!$shortlink) {
      $short_properties = [
        'hash' => $hash,
        'redirect_source' => $url->toString(),
        'shortened_link' => '/' . $this->generateRandomString()
      ];
      $shortlink = $this->entityTypeManager->getStorage('shortenerredirect')->create($short_properties);
      $shortlink->save();
    }

    return $shortlink;
  }

  /**
   * Check if short link exists.
   *
   * @param $hash
   * @return \Drupal\Core\Entity\EntityInterface|false|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function shortLinkExists($hash) {
    $entity_storage = $this->entityTypeManager->getStorage('shortenerredirect');
    $result = $entity_storage->getQuery()
      ->condition('hash', $hash)
      ->execute();
    if (empty($result)) {
      return FALSE;
    }
    return $entity_storage->load(reset($result));
  }

  /**
   * Generate random alphanumeric string.
   *
   * @param $length
   * @return string
   */
  protected function generateRandomString($length = 6){
    $chars = "abcdfghjkmnpqrstvwxyz|ABCDFGHJKLMNPQRSTVWXYZ|0123456789";
    $sets = explode('|', $chars);
    $all = '';
    $randString = '';
    foreach($sets as $set){
      $randString .= $set[array_rand(str_split($set))];
      $all .= $set;
    }
    $all = str_split($all);
    for($i = 0; $i < $length - count($sets); $i++){
      $randString .= $all[array_rand($all)];
    }
    $randString = str_shuffle($randString);
    return $randString;
  }

}
