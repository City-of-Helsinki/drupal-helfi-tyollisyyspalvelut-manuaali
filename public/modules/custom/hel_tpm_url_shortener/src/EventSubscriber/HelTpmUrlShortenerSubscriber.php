<?php

namespace Drupal\hel_tpm_url_shortener\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

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
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  private Request $request;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Time interface.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private TimeInterface $time;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time interface.
   */
  public function __construct(MessengerInterface $messenger, RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    $this->messenger = $messenger;
    $this->request = $request_stack->getCurrentRequest();
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function onKernelRequest(RequestEvent $event) {
    $uri = $this->request->getPathInfo();
    $storage = $this->entityTypeManager->getStorage('shortenerredirect');
    $result = $storage->getQuery()
      ->condition('shortened_link', $uri)
      ->accessCheck()
      ->execute();
    if (empty($result)) {
      return FALSE;
    }
    $link = $storage->load(reset($result));
    $source = $link->getRedirectSource();
    // Set last usage time to link.
    $link->setLastUsage($this->time->getRequestTime());
    $link->save();
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
