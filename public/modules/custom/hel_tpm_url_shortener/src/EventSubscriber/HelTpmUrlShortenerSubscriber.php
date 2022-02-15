<?php

namespace Drupal\hel_tpm_url_shortener\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Hel TPM Url shortener event subscriber.
 */
class HelTpmUrlShortenerSubscriber implements EventSubscriberInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * Kernel request event handler.
   *
   * @param ResponseEvent $event
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onKernelRequest(RequestEvent $event) {
    $uri = \Drupal::request()->getPathInfo();
    $storage = \Drupal::entityTypeManager()->getStorage('shortenerredirect');
    $result = $storage->getQuery()->condition('shortened_link', $uri)->execute();
    if (empty($result)) {
      return FALSE;
    }
    $link = $storage->load(reset($result));
    $source = $link->getRedirectSource();
    $response = new RedirectResponse($source);
    $response->send();

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onKernelRequest'],
    ];
  }

}
