<?php

namespace Drupal\hel_tpm_editorial\Plugin\views\field;

use Drupal\content_moderation\Entity\ContentModerationState;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\Plugin\Field\ModerationStateFieldItemList;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModerationInformationInterface $moderation_information) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->moderationInformation = $moderation_information;
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
      $container->get('content_moderation.moderation_information')
    );
  }

  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity;
    if (!$entity instanceof NodeInterface) {
      return [];
    }
    if ($entity->bundle() !== 'service') {
      return [];
    }

    if (!$entity->isLatestRevision()) {
      $moderation_state = $this->getLatestRevisionModerationState($entity);
      return [
        '#theme' => 'service_has_changes_field',
        '#link' => $this->linkGenerator()->generate('Unpublished changes', $this->latestRevisionUrl($entity)),
        '#state' => $moderation_state,
      ];
    }
    return ['#markup' => $this->t('Up to date')];
  }

  /**
   * Get moderation state for latest revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return string
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getLatestRevisionModerationState(NodeInterface $node) {
    $rev = $this->getLatestRevision($node);
    return $this->getStateLabel($rev);
  }

  /**
   * Get latest revision.
   *
   * @param $node
   *
   * @return mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getLatestRevision($node) {
    $storage = $this->entityTypeManager->getStorage($node->getEntityTypeId());
    $langcode = $node->language()->getId();
    // Load latesta node revision.
    $revision_id = $storage->getLatestTranslationAffectedRevisionId($node->id(), $langcode);
    $revision = $storage->loadRevision($revision_id);
    // Load translation for current language
    $translation = $revision->getTranslation($langcode);
    return $translation;
  }

  /**
   * Get state label.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return string
   */
  protected function getStateLabel(NodeInterface $node) {
    $state = ContentModerationState::loadFromModeratedEntity($node);
    $workflow = $this->moderationInformation->getWorkflowForEntity($node);
    $moderation_state = $state->moderation_state->value;
    return $workflow->getTypePlugin()->getState($moderation_state)->label();
  }
  /**
   * Generate latest revision url.
   *
   * @param \Drupal\node\NodeInterface $entity
   *
   * @return \Drupal\Core\Url
   */
  protected function latestRevisionUrl(NodeInterface $entity) {
    $url = sprintf('/node/%s/latest', $entity->id());
    return Url::fromUserInput($url);
  }
}
