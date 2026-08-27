<?php

namespace Drupal\entity_diff_ui\Plugin\diff\Layout;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\diff\Plugin\diff\Layout\UnifiedFieldsDiffLayout;

/**
 * Defines the layout for unified fields difference on entities.
 *
 * This class extends the UnifiedFieldsDiffLayout to provide specific
 * functionality for rendering differences in entity revisions,
 * including details such as author, date, and revision messages.
 */
class EntityUnifiedFieldsDiffLayout extends UnifiedFieldsDiffLayout {

  /**
   * {@inheritdoc}
   */
  protected function buildRevisionData(ContentEntityInterface $revision) {
    if ($revision instanceof RevisionLogInterface) {
      $revision_log = Xss::filter($revision->getRevisionLogMessage());
      $revision_user = $revision->getRevisionUser();
      $user_id = $revision->getRevisionUserId();

      if ($revision_user) {
        $user_name = $revision_user->getDisplayName();
      }
      else {
        $user_name = $this->t('Anonymous (not verified)');
      }

      $revision_link['date'] = [
        '#type' => 'link',
        '#title' => $this->date->format($revision->getRevisionCreationTime(), 'short'),
        '#url' => $revision->toUrl('revision'),
        '#prefix' => '<div class="diff-revision__item diff-revision__item-date">',
        '#suffix' => '</div>',
      ];

      $revision_link['author'] = [
        '#type' => 'link',
        '#title' => $user_name,
        '#url' => Url::fromUri(\Drupal::request()->getUriForPath('/user/' . $user_id)),
        '#theme' => 'username',
        '#account' => $revision_user,
        '#prefix' => '<div class="diff-revision__item diff-revision__item-author">',
        '#suffix' => '</div>',
      ];

      if ($revision_log) {
        $revision_link['message'] = [
          '#type' => 'markup',
          '#prefix' => '<div class="diff-revision__item diff-revision__item-message">',
          '#suffix' => '</div>',
          '#markup' => $revision_log,
        ];
      }
    }
    else {
      $revision_link['label'] = [
        '#type' => 'link',
        '#title' => $revision->label(),
        '#url' => $revision->toUrl('revision'),
        '#prefix' => '<div class="diff-revision__item diff-revision__item-date">',
        '#suffix' => '</div>',
      ];
    }
    return $revision_link;
  }

}
