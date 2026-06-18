<?php

namespace Drupal\media_diff_ui\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_diff_ui\Utility\RevisionOperationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a media revision.
 *
 * @internal
 */
class MediaRevisionDeleteForm extends ConfirmFormBase {

  use RevisionOperationTrait;

  /**
   * The media revision.
   *
   * @var \Drupal\Core\Entity\EditorialContentEntityBase
   */
  protected $revision;

  /**
   * The media storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaStorage;

  /**
   * The media type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaTypeStorage;

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
   * Constructs a new MediaRevisionDeleteForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $media_storage
   *   The media storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $media_type_storage
   *   The media type storage.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(EntityStorageInterface $media_storage, EntityStorageInterface $media_type_storage, Connection $connection, DateFormatterInterface $date_formatter) {
    $this->mediaStorage = $media_storage;
    $this->mediaTypeStorage = $media_type_storage;
    $this->connection = $connection;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type_manager->getStorage('media'),
      $entity_type_manager->getStorage('media_type'),
      $container->get('database'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_revision_delete_confirm';
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
    return new Url('entity.media.version_history', ['media' => $this->revision->id()]);
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
  public function buildForm(array $form, FormStateInterface $form_state, $media_revision = NULL) {
    $this->revision = $this->mediaStorage->loadRevision($media_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->revisionDelete($this->mediaStorage, $this->mediaTypeStorage, $this->revision, $this->logger('media'), $this->messenger());
    $form_state->setRedirect(
        'entity.media.canonical',
        ['media' => $this->revision->id()]
        );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {media_field_revision} WHERE mid = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.media.version_history',
        ['media' => $this->revision->id()]
      );
    }
  }

}
