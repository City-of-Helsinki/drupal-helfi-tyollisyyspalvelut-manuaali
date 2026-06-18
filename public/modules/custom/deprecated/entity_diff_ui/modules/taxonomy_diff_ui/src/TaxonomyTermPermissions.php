<?php

namespace Drupal\taxonomy_diff_ui;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions for nodes of different types.
 */
class TaxonomyTermPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * TaxonomyTermPermissions constructor.
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
   * Returns an array of taxonomy term type permissions.
   *
   * @return array
   *   The vocabulary type permissions.
   *   @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function taxonomyTypePermissions() {
    $perms = [];
    // Generate taxonomy term permissions for all term types.
    $term_types = $this->entityTypeManager
      ->getStorage('taxonomy_vocabulary')->loadMultiple();
    foreach ($term_types as $type) {
      $perms += $this->buildPermissions($type);
    }
    return $perms;
  }

  /**
   * Returns a list of taxonomy term permissions for a given vocabulary.
   *
   * @param \Drupal\Core\Entity\EntityInterface $type
   *   The vocabulary type.
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
        'description' => $this->t('To view a revision, you also need permission to view the taxonomy term.'),
      ],
      "revert $type_id revisions" => [
        'title' => $this->t('%type_name: Revert revisions', $type_params),
        'description' => $this->t('To revert a revision, you also need permission to edit the taxonomy term.'),
      ],
      "delete $type_id revisions" => [
        'title' => $this->t('%type_name: Delete revisions', $type_params),
        'description' => $this->t('To delete a revision, you also need permission to delete the taxonomy term.'),
      ],
    ];
  }

}
