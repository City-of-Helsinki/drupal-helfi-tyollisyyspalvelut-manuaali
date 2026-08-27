<?php

namespace Drupal\entity_diff_ui\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\diff\DiffEntityComparison;
use Drupal\diff\DiffLayoutManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Entity\EditorialContentEntityBase;

/**
 * Provides a form for revision overview page.
 */
class RevisionOverviewForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The date service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $date;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Wrapper object for simple configuration from diff.settings.yml.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The field diff layout plugin manager service.
   *
   * @var \Drupal\diff\DiffLayoutManager
   */
  protected $diffLayoutManager;

  /**
   * The diff entity comparison service.
   *
   * @var \Drupal\diff\DiffEntityComparison
   */
  protected $entityComparison;

  /**
   * Constructs a RevisionOverviewForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Datetime\DateFormatter $date
   *   The date service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\diff\DiffLayoutManager $diff_layout_manager
   *   The diff layout service.
   * @param \Drupal\diff\DiffEntityComparison $entity_comparison
   *   The diff entity comparison service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, DateFormatter $date, RendererInterface $renderer, LanguageManagerInterface $language_manager, DiffLayoutManager $diff_layout_manager, DiffEntityComparison $entity_comparison) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->date = $date;
    $this->renderer = $renderer;
    $this->languageManager = $language_manager;
    $this->config = $this->config('diff.settings');
    $this->diffLayoutManager = $diff_layout_manager;
    $this->entityComparison = $entity_comparison;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('entity_type.manager'),
        $container->get('current_user'),
        $container->get('date.formatter'),
        $container->get('renderer'),
        $container->get('language_manager'),
        $container->get('plugin.manager.diff.layout'),
        $container->get('diff.entity_comparison')
        );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_diff_revision_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EditorialContentEntityBase $entity = NULL) {
    $account = $this->currentUser;
    $langcode = $entity->language()->getId();
    $langname = $entity->language()->getName();
    $languages = $entity->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $entity_type_id = $entity->getEntityTypeId();
    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    $type = $entity->bundle();

    $pagerLimit = $this->config->get('general_settings.revision_pager_limit');

    $query = $entity_storage->getQuery()
      ->condition($entity->getEntityType()->getKey('id'), $entity->id())
      ->pager($pagerLimit)
      ->allRevisions()
      ->sort($entity->getEntityType()->getKey('revision'), 'DESC')
      ->accessCheck(TRUE)
      ->execute();
    $vids = array_keys($query);

    $revision_count = count($vids);

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', [
      '@langname' => $langname,
      '%title' => $entity->label(),
    ]) : $this->t('Revisions for %title', [
      '%title' => $entity->label(),
    ]);
    $build['eid'] = [
      '#type' => 'value',
      '#value' => $entity->id(),
    ];
    $build['entity_type'] = [
      '#type' => 'value',
      '#value' => $entity_type_id,
    ];

    $table_header = [];
    $table_header['revision'] = $this->t('Revision');

    // Allow comparisons only if there are 2 or more revisions.
    if ($revision_count > 1) {
      $table_header += [
        'select_column_one' => '',
        'select_column_two' => '',
      ];
    }
    $table_header['operations'] = $this->t('Operations');

    $rev_revert_perm = $account->hasPermission("revert $type revisions") ||
    $account->hasPermission("revert all $entity_type_id revisions");
    $rev_delete_perm = $account->hasPermission("delete $type revisions") ||
    $account->hasPermission("delete all $entity_type_id revisions");
    $revert_permission = $rev_revert_perm && $entity->access('update');
    $delete_permission = $rev_delete_perm && $entity->access('delete');

    // Contains the table listing the revisions.
    $build['entity_revisions_table'] = [
      '#type' => 'table',
      '#header' => $table_header,
      '#attributes' => ['class' => ['diff-revisions']],
    ];

    $build['entity_revisions_table']['#attached']['library'][] = 'diff/diff.general';
    $build['entity_revisions_table']['#attached']['drupalSettings']['diffRevisionRadios'] = $this->config->get('general_settings.radio_behavior');

    $default_revision = $entity->getRevisionId();
    // Add rows to the table.
    foreach ($vids as $key => $vid) {
      $previous_revision = NULL;
      if (isset($vids[$key + 1])) {
        $previous_revision = $entity_storage->loadRevision($vids[$key + 1]);
      }
      /** @var \Drupal\Core\Entity\ContentEntityInterface $revision */
      if ($revision = $entity_storage->loadRevision($vid)) {
        if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
          $username = [
            '#theme' => 'username',
            '#account' => $revision->getRevisionUser(),
          ];
          $revision_date = $this->date->format($revision->getRevisionCreationTime(), 'short');
          // Use revision link to link to revisions that are not active.
          if ($vid != $entity->getRevisionId()) {
            $link = Link::fromTextAndUrl($revision_date, new Url("entity.$entity_type_id.revision", [
              $entity_type_id => $entity->id(),
              $entity_type_id . '_revision' => $vid,
            ]));
          }
          else {
            $link = $entity->toLink($revision_date);
          }

          if ($vid == $default_revision) {
            $row = [
              'revision' => $this->buildRevision($link, $username, $revision, $previous_revision),
            ];

            // Allow comparisons only if there are 2 or more revisions.
            if ($revision_count > 1) {
              $row += [
                'select_column_one' => $this->buildSelectColumn('radios_left', $vid, FALSE),
                'select_column_two' => $this->buildSelectColumn('radios_right', $vid, $vid),
              ];
            }
            $row['operations'] = [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
              '#attributes' => [
                'class' => ['revision-current'],
              ],
            ];
            $row['#attributes'] = [
              'class' => ['revision-current'],
            ];
          }
          else {
            $route_params = [
              $entity_type_id => $entity->id(),
              $entity_type_id . '_revision' => $vid,
              'langcode' => $langcode,
            ];
            $links = [];
            if ($revert_permission) {
              $links['revert'] = [
                'title' => $vid < $entity->getRevisionId() ? $this->t('Revert') : $this->t('Set as current revision'),
                'url' => $has_translations ?
                Url::fromRoute("$entity_type_id.revision_revert_translation_confirm", [
                  $entity_type_id => $entity->id(),
                  $entity_type_id . '_revision' => $vid,
                  'langcode' => $langcode,
                ]) :
                Url::fromRoute("$entity_type_id.revision_revert_confirm", [
                  $entity_type_id => $entity->id(),
                  $entity_type_id . '_revision' => $vid,
                ]
                ),
              ];
            }
            if ($delete_permission) {
              $links['delete'] = [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute("$entity_type_id.revision_delete_confirm", $route_params),
              ];
            }

            // Here we don't have to deal with 'only one revision' case because
            // if there's only one revision it will also be the default one,
            // entering on the first branch of this if else statement.
            $row = [
              'revision' => $this->buildRevision($link, $username, $revision, $previous_revision),
              'select_column_one' => $this->buildSelectColumn('radios_left', $vid,
                  $vids[1] ?? FALSE),
              'select_column_two' => $this->buildSelectColumn('radios_right', $vid, FALSE),
              'operations' => [
                '#type' => 'operations',
                '#links' => $links,
              ],
            ];
          }
          // Add the row to the table.
          $build['entity_revisions_table'][] = $row;
        }
      }
    }

    // Allow comparisons only if there are 2 or more revisions.
    if ($revision_count > 1) {
      $build['submit'] = [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Compare selected revisions'),
        '#attributes' => [
          'class' => [
            'diff-button',
          ],
        ],
      ];
    }
    $build['pager'] = [
      '#type' => 'pager',
    ];
    // @todo Replace the css from node module.
    $build['#attached']['library'][] = 'node/drupal.node.admin';
    return $build;
  }

  /**
   * Set column attributes and return config array.
   *
   * @param string $name
   *   Name attribute.
   * @param string $return_val
   *   Return value attribute.
   * @param string $default_val
   *   Default value attribute.
   *
   * @return array
   *   Configuration array.
   */
  protected function buildSelectColumn($name, $return_val, $default_val) {
    return [
      '#type' => 'radio',
      '#title_display' => 'invisible',
      '#name' => $name,
      '#return_value' => $return_val,
      '#default_value' => $default_val,
    ];
  }

  /**
   * Set and return configuration for revision.
   *
   * @param \Drupal\Core\Link $link
   *   Link attribute.
   * @param string $username
   *   Username attribute.
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   Revision parameter for getRevisionDescription function.
   * @param \Drupal\Core\Entity\ContentEntityInterface $previous_revision
   *   (optional) Previous revision for getRevisionDescription function.
   *   Defaults to NULL.
   *
   * @return array
   *   Configuration for revision.
   */
  protected function buildRevision(Link $link, $username, ContentEntityInterface $revision, ?ContentEntityInterface $previous_revision = NULL) {
    return [
      '#type' => 'inline_template',
      '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
      '#context' => [
        'date' => $link->toString(),
        'username' => $this->renderer->renderPlain($username),
        'message' => [
          '#markup' => $this->entityComparison->getRevisionDescription($revision, $previous_revision),
          '#allowed_tags' => Xss::getAdminTagList(),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();

    if (count($form_state->getValue('entity_revisions_table')) <= 1) {
      $form_state->setErrorByName('entity_revisions_table', $this->t('Multiple revisions are needed for comparison.'));
    }
    elseif (!isset($input['radios_left']) || !isset($input['radios_right'])) {
      $form_state->setErrorByName('entity_revisions_table', $this->t('Select two revisions to compare.'));
    }
    elseif ($input['radios_left'] == $input['radios_right']) {
      // @todo Radio-boxes selection resets if there are errors.
      $form_state->setErrorByName('entity_revisions_table', $this->t('Select different revisions to compare.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $value = $form_state->getValues();
    $input = $form_state->getUserInput();
    $vid_left = $input['radios_left'];
    $vid_right = $input['radios_right'];
    $eid = $value['eid'];
    $entity_type = $value['entity_type'];

    // Always place the older revision on the left side of the comparison
    // and the newer revision on the right side (however revisions can be
    // compared both ways if we manually change the order of the parameters).
    if ($vid_left > $vid_right) {
      $aux = $vid_left;
      $vid_left = $vid_right;
      $vid_right = $aux;
    }
    // Builds the redirect Url.
    $redirect_url = Url::fromRoute(
        "entity.$entity_type.revisions_diff",
        [
          $entity_type => $eid,
          'left_revision' => $vid_left,
          'right_revision' => $vid_right,
          'filter' => $this->diffLayoutManager->getDefaultLayout(),
        ]
        );
    $form_state->setRedirectUrl($redirect_url);
  }

}
