<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_group\Plugin\views\field;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess;
use Drupal\views\Plugin\views\field\LinkBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Group Admin Reactivate Member field handler.
 *
 * @ViewsField("hel_tpm_group_reactivate_group_user_link")
 *
 * @DCG
 * The plugin needs to be assigned to a specific table column through
 * hook_views_data() or hook_views_data_alter().
 * Put the following code to hel_tpm_group.views.inc file.
 * @code
 * function foo_views_data_alter(array &$data): void {
 *   $data['node']['foo_example']['field'] = [
 *     'title' => t('Example'),
 *     'help' => t('Custom example field.'),
 *     'id' => 'foo_example',
 *   ];
 * }
 * @endcode
 */
final class GroupAdminReactivateMemberLink extends LinkBase {

  /**
   * Reactivate group member access service.
   *
   * @var \Drupal\hel_tpm_group\Access\ReactivateGroupMemberAccess
   */
  private ReactivateGroupMemberAccess $reactivateGroupMemberFormAccess;

  /**
   * Current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  private CurrentPathStack $pathCurrent;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AccessManagerInterface $access_manager,
    EntityTypeManagerInterface $entity_type_manager,
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    ReactivateGroupMemberAccess $reactivate_group_member_form_access,
    CurrentPathStack $path_current,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $access_manager, $entity_type_manager, $entity_repository, $language_manager);
    $this->reactivateGroupMemberFormAccess = $reactivate_group_member_form_access;
    $this->pathCurrent = $path_current;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('access_manager'),
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('hel_tpm_group.reactivate_group_member_access'),
      $container->get('path.current')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {}

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row) {
    return $this->createReactivationLink($row);
  }

  /**
   * Creates a reactivation link for a given group member.
   *
   * Generates a URL to reactivate a group member and returns the link
   * as a renderable array if accessible.
   *
   * @param \Drupal\Core\Database\ResultRow $row
   *   The row object representing the group member data.
   *
   * @return array
   *   A renderable array of the reactivation link if accessible,
   *   or an empty array if the link is not accessible.
   */
  protected function createReactivationLink(ResultRow $row): array {
    $url = Url::fromRoute('hel_tpm_group.reactivate_group_member',
      [
        'group' => $row->_entity->gid->target_id,
        'group_content' => $row->_entity->id(),
      ],
    );

    $url->setOption('query', ['destination' => $this->pathCurrent->getPath()]);

    if (!$url->access()) {
      return [];
    }
    $link = Link::fromTextAndUrl($this->t('Reactivate'), $url);
    return $link->toRenderable();
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    $group = $this->getGroup();
    if (empty($group)) {
      return AccessResult::forbidden();
    }
    if ($this->reactivateGroupMemberFormAccess->canActivateGroupMembers($account, $group)) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * Retrieves a group entity based on the argument provided by the view.
   *
   * If no argument is provided or it is empty, returns NULL. When an argument
   * is present, it attempts to load a group entity using the argument as the
   * group ID.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The loaded group entity if found, or NULL if the argument is absent or
   *   the group cannot be loaded.
   */
  protected function getGroup() {
    if (empty($this->view->args[0])) {
      return NULL;
    }
    $group_id = $this->view->args[0];
    return $this->entityTypeManager->getStorage('group')->load($group_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function getUrlInfo(ResultRow $row) {}

}
