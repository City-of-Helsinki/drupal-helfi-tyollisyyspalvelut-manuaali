<?php

namespace Drupal\media_diff_ui;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions for media content of different types.
 */
class MediaRevisionPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * MediaRevisionPermissions constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Returns an array of media content type permissions.
   *
   * @return array
   *   The media content type permissions.
   *   @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function mediaTypePermissions() {
    $perms = [];
    // Generate media content permissions for all media content types.
    $media_types = $this->entityTypeManager
      ->getStorage('media_type')->loadMultiple();
    foreach ($media_types as $type) {
      $perms += $this->buildPermissions($type);
    }
    return $perms;
  }

  /**
   * Returns a list of media content permissions for a given media type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $type
   *   The media type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(EntityInterface $type) {
    $type_id = $type->id();
    $type_params = ['%type_name' => $type->label()];

    return [
      "view $type_id revisions" => [
        'title' => $this->t('%type_name: View revisions', $type_params),
        'description' => $this->t('To view a revision, you also need permission to view the media content.'),
      ],
      "revert $type_id revisions" => [
        'title' => $this->t('%type_name: Revert revisions', $type_params),
        'description' => $this->t('To revert a revision, you also need permission to edit the media content.'),
      ],
      "delete $type_id revisions" => [
        'title' => $this->t('%type_name: Delete revisions', $type_params),
        'description' => $this->t('To delete a revision, you also need permission to delete the media content.'),
      ],
    ];
  }

}
