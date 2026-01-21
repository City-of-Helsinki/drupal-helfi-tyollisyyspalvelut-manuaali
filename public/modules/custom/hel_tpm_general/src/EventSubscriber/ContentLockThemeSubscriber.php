<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Routing\Router;

/**
 * Class to change the proper theme for content break lock form.
 */
final class ContentLockThemeSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a HelTpmContentLockThemeSubscriber object.
   */
  public function __construct(
    private readonly ThemeManagerInterface $themeManager,
    private readonly ThemeInitializationInterface $themeInitialization,
    private readonly Router $router,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Handles the kernel request event to manage route-specific functionality.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to respond to, from which the request is obtained.
   *
   * @return void
   *   Return nothing.
   */
  public function onKernelRequest(RequestEvent $event): void {
    $request = $event->getRequest();

    try {
      $route_object = $this->router->matchRequest($request, []);
    }
    catch (\Exception $e) {
      return;
    }

    if (!empty($route_object['_route']) && in_array($route_object['_route'], $this->getRoutes(), TRUE)) {
      $system_theme = $this->configFactory->get('system.theme');
      $this->themeManager->setActiveTheme($this->themeInitialization->initTheme($system_theme->get('default')));
    }
  }

  /**
   * Retrieves an array of route names.
   *
   * @return array
   *   An array of route names.
   */
  private function getRoutes() {
    $routes = ['content_lock.break_lock.node'];
    return $routes;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onKernelRequest'],
    ];
  }

}
