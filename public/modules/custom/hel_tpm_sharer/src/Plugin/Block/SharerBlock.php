<?php

namespace Drupal\hel_tpm_sharer\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Provides a sharer block.
 *
 * @Block(
 *   id = "hel_sharer_block",
 *   admin_label = @Translation("Sharer block"),
 *   category = "sharer",
 * )
 */
class SharerBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $output = [];
    $entity = \Drupal::routeMatch()->getParameter('node');
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
