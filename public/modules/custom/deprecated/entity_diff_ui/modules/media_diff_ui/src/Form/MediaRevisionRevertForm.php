<?php

namespace Drupal\media_diff_ui\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_diff_ui\Utility\RevisionOperationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a media revision.
 *
 * @internal
 */
class MediaRevisionRevertForm extends ConfirmFormBase {

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
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new MediaRevisionRevertForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $media_storage
   *   The media storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $media_type_storage
   *   The media type storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityStorageInterface $media_storage, EntityStorageInterface $media_type_storage, DateFormatterInterface $date_formatter, TimeInterface $time) {
    $this->mediaStorage = $media_storage;
    $this->mediaTypeStorage = $media_type_storage;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('media'),
      $container->get('entity_type.manager')->getStorage('media_type'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_revision_revert_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to revert to the revision from %revision-date?', ['%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime())]);
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
    return $this->t('Revert');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
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
    $this->revisionRevert($this->mediaTypeStorage, $this->revision, $this->logger('media'), $this->messenger(), $this->currentUser()->id(), $form_state);
    $form_state->setRedirect(
      'entity.media.version_history',
      ['media' => $this->revision->id()]
    );
  }

}
