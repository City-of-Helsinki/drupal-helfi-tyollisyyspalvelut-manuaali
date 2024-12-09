<?php

namespace Drupal\hel_tpm_sharer\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a sharer block.
 *
 * @Block(
 *   id = "hel_sharer_block",
 *   admin_label = @Translation("Sharer block"),
 *   category = "sharer",
 * )
 */
class SharerBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Route match interface.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  private RouteMatchInterface $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $output = [];
    $entity = $this->routeMatch->getParameter('node');
    if ($entity instanceof NodeInterface) {
      $vars = [
        ':title' => $entity->getTitle(),
        ':url' => $entity->toUrl()->setAbsolute()->toString(),
        ':desc' => $entity->get('field_description')->value,
      ];
      $subject = $this->t('Shared service: :title', $vars);
      $message = $this->t("Take a look at this service: :title (:url).\n\n:desc", $vars);
      $mailtoUrl = Url::fromUri('mailto:', [
        'query' => [
          'subject' => $subject,
          'body' => $message,
        ],
      ]);

      $output['#markup'] = Link::fromTextAndUrl($this->t('Share'), $mailtoUrl)->toString();
    }
    return $output;
  }

}
