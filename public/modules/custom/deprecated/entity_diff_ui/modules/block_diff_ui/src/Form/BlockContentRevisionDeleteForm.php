<?php

namespace Drupal\block_diff_ui\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_diff_ui\Utility\RevisionOperationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a block content revision.
 *
 * @internal
 */
class BlockContentRevisionDeleteForm extends ConfirmFormBase {

  use RevisionOperationTrait;

  /**
   * The block content revision.
   *
   * @var \Drupal\Core\Entity\EditorialContentEntityBase
   */
  protected $revision;

  /**
   * The block content storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $blockStorage;

  /**
   * The block type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $blockTypeStorage;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new BlockContentRevisionDeleteForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $block_content_storage
   *   The block content storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $block_type_storage
   *   The block type storage.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(EntityStorageInterface $block_content_storage, EntityStorageInterface $block_type_storage, Connection $connection, DateFormatterInterface $date_formatter) {
    $this->blockStorage = $block_content_storage;
    $this->blockTypeStorage = $block_type_storage;
    $this->connection = $connection;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type_manager->getStorage('block_content'),
      $entity_type_manager->getStorage('block_content_type'),
      $container->get('database'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'block_content_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the revision from %revision-date?', [
      '%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.block_content.version_history', ['block_content' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $block_content_revision = NULL) {
    $this->revision = $this->blockStorage->loadRevision($block_content_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->revisionDelete($this->blockStorage, $this->blockTypeStorage, $this->revision, $this->logger('block_content'), $this->messenger());
    $form_state->setRedirect(
        'entity.block_content.canonical',
        ['block_content' => $this->revision->id()]
        );
    if ($this->connection->query('SELECT COUNT(DISTINCT revision_id) FROM {block_content_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.block_content.version_history',
        ['block_content' => $this->revision->id()]
      );
    }
  }

}
