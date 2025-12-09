<?php

namespace Drupal\hel_tpm_editorial\Plugin\views\field;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Service has unpublished changes field handler.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("hel_tpm_editorial_service_has_unpublished_changes")
 *
 * @DCG
 * The plugin needs to be assigned to a specific table column through
 * hook_views_data() or hook_views_data_alter().
 * For non-existent columns (i.e. computed fields) you need to override
 * self::query() method.
 */
class ServiceHasUnpublishedChanges extends FieldPluginBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Moderation information interface.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  private ModerationInformationInterface $moderationInformation;

  /**
   * Database connection interface.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    ModerationInformationInterface $moderation_information,
    Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationInformation = $moderation_information;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('content_moderation.moderation_information'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity;
    $langcode = !empty($values->node_field_data_langcode) ? $values->node_field_data_langcode : '';

    if (!empty($langcode) && $entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }

    if (!$entity instanceof NodeInterface) {
      return [];
    }
    if ($entity->bundle() !== 'service') {
      return [];
    }

    if (!$entity->isLatestTranslationAffectedRevision()) {
      $latest_translation_affected = $this->getLatestTranslationAffectedRevision($entity);
      if (!empty($latest_translation_affected)) {
        if ($entity->get('moderation_state')->value !== $latest_translation_affected->get('moderation_state')->value) {
          $moderation_state = $this->getLatestRevisionModerationState($entity);
          return [
            '#theme' => 'service_has_changes_field',
            '#link' => $this->linkGenerator()
              ->generate('Unpublished changes', $this->latestRevisionUrl($entity)),
            '#state' => $moderation_state,
            '#changed' => $this->getLatestChanged($entity),
          ];
        }
      }
    }
    return ['#markup' => $this->t('Up to date')];
  }

  /**
   * Get moderation state for latest revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node entity.
   *
   * @return string
   *   State label from latest revision.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getLatestRevisionModerationState(NodeInterface $node) {
    $rev = $this->getLatestRevision($node);
    return $this->getStateLabel($rev);
  }

  /**
   * Get latest translation affected revision row.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node interface.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Entity revision or null.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getLatestTranslationAffectedRevision(NodeInterface $node) {
    $database = $this->database;
    $vid = $database->select('node_field_revision', 'n')
      ->fields('n', ['vid'])
      ->condition('nid', $node->id())
      ->condition('langcode', $node->language()->getId())
      ->condition('revision_translation_affected', 1)
      ->orderBy('vid', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc('vid');

    if (empty($vid)) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('node');

    $revision = $storage->loadRevision(reset($vid));
    if ($revision->hasTranslation($node->language()->getId())) {
      $revision = $revision->getTranslation($node->language()->getId());
    }

    return $revision;
  }

  /**
   * Get latest revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node interface.
   *
   * @return mixed
   *   Latest node revision.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getLatestRevision($node) : NodeInterface {
    $storage = $this->entityTypeManager->getStorage($node->getEntityTypeId());
    $langcode = $node->language()->getId();
    // Load latest node revision.
    $revision_id = $storage->getLatestTranslationAffectedRevisionId($node->id(), $langcode);
    $revision = $storage->loadRevision($revision_id);
    // Load translation for current language.
    return $revision->getTranslation($langcode);
  }

  /**
   * Get state label.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node interface.
   *
   * @return string
   *   Node workflow state.
   */
  protected function getStateLabel(NodeInterface $node) {
    $moderation_state = $node->get('moderation_state')->value;
    $workflow = $this->moderationInformation->getWorkflowForEntity($node);
    return $workflow->getTypePlugin()->getState($moderation_state)->label();
  }

  /**
   * Generate latest revision url.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   Node interface.
   *
   * @return \Drupal\Core\Url
   *   Url to latesta node version.
   */
  protected function latestRevisionUrl(NodeInterface $entity) {
    $url = sprintf('/node/%s/latest', $entity->id());
    return Url::fromUserInput($url);
  }

  /**
   * Retrieves the latest changed time for the provided entity.
   *
   * @param \NodeInterface $entity
   *   The entity for which the latest changed time is being retrieved.
   *
   * @return int
   *   The timestamp of the latest changed time.
   */
  protected function getLatestChanged(NodeInterface $entity) {
    $revision = $this->getLatestRevision($entity);
    return $revision->getChangedTime();
  }

}
