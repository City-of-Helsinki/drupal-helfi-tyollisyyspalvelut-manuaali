<?php

namespace Drupal\entity_diff_ui\Plugin\diff\Layout;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\diff\Controller\PluginRevisionController;
use Drupal\diff\Plugin\diff\Layout\VisualInlineDiffLayout;

/**
 * Defines the EntityVisualInlineDiffLayout class for visual inline differences.
 */
class EntityVisualInlineDiffLayout extends VisualInlineDiffLayout {

  /**
   * {@inheritdoc}
   */
  protected function buildRevisionData(ContentEntityInterface $revision) {
    if ($revision instanceof RevisionLogInterface) {
      $revision_log_message = $revision->getRevisionLogMessage();
      $revision_log = !empty($revision_log_message) ? Xss::filter($revision_log_message) : '';
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

  /**
   * {@inheritdoc}
   */
  public function build(ContentEntityInterface $left_revision, ContentEntityInterface $right_revision, ContentEntityInterface $entity) {
    // Build the revisions data.
    $build = $this->buildRevisionsData($left_revision, $right_revision);

    $this->entityTypeManager->getStorage($entity->getEntityTypeId())->resetCache([$entity->id()]);
    // Build the view modes filter.
    $options = [];
    // Get all view modes for entity type.
    $view_modes = $this->entityDisplayRepository->getViewModeOptionsByBundle($entity->getEntityTypeId(), $entity->bundle());
    if (empty($view_modes)) {
      // There is no any view modes for the entity bundle specified.
      // Use the default view mode of the entity instead.
      $view_modes = $this->entityDisplayRepository->getViewModeOptions($entity->getEntityTypeId());
    }
    foreach ($view_modes as $view_mode => $view_mode_info) {
      // Skip view modes that are not used in the front end.
      if (in_array($view_mode, ['rss', 'search_index'])) {
        continue;
      }
      $options[$view_mode] = [
        'title' => $view_mode_info,
        'url' => PluginRevisionController::diffRoute($entity,
            $left_revision->getRevisionId(),
            $right_revision->getRevisionId(),
            'visual_inline',
            ['view_mode' => $view_mode]
        ),
      ];
    }

    $active_option = array_keys($options);
    $active_view_mode = $this->requestStack->getCurrentRequest()->query->get('view_mode') ?: reset($active_option);

    $filter = $options[$active_view_mode];
    unset($options[$active_view_mode]);
    array_unshift($options, $filter);

    $build['controls']['view_mode'] = [
      '#type' => 'item',
      '#title' => $this->t('View mode'),
      '#wrapper_attributes' => ['class' => 'diff-controls__item'],
      'filter' => [
        '#type' => 'operations',
        '#links' => $options,
      ],
    ];

    $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
    // Trigger exclusion of interactive items like on preview.
    $left_revision->in_preview = TRUE;
    $right_revision->in_preview = TRUE;
    $left_view = $view_builder->view($left_revision, $active_view_mode);
    $right_view = $view_builder->view($right_revision, $active_view_mode);

    // Avoid render cache from being built.
    unset($left_view['#cache']);
    unset($right_view['#cache']);

    $html_1 = $this->renderer->render($left_view);
    $html_2 = $this->renderer->render($right_view);

    $this->htmlDiff->setOldHtml($html_1);
    $this->htmlDiff->setNewHtml($html_2);
    $this->htmlDiff->build();

    $build['diff'] = [
      '#markup' => $this->htmlDiff->getDifference(),
      '#weight' => 10,
    ];

    $build['#attached']['library'][] = 'diff/diff.visual_inline';
    return $build;
  }

}
