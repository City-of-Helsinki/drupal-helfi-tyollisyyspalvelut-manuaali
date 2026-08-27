<?php

namespace Drupal\block_diff_ui\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_diff_ui\Utility\RevisionOperationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a block content revision.
 *
 * @internal
 */
class BlockContentRevisionRevertForm extends ConfirmFormBase {

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
  protected $blockContentStorage;

  /**
   * The block content type storage.
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
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new BlockContentRevisionRevertForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $block_content_storage
   *   The block content storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $block_content_type_storage
   *   The block content type storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityStorageInterface $block_content_storage, EntityStorageInterface $block_content_type_storage, DateFormatterInterface $date_formatter, TimeInterface $time) {
    $this->blockContentStorage = $block_content_storage;
    $this->blockTypeStorage = $block_content_type_storage;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('block_content'),
      $container->get('entity_type.manager')->getStorage('block_content_type'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'block_content_revision_revert_confirm';
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
    return new Url('entity.block_content.version_history', ['block_content' => $this->revision->id()]);
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
  public function buildForm(array $form, FormStateInterface $form_state, $block_content_revision = NULL) {
    $this->revision = $this->blockContentStorage->loadRevision($block_content_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->revisionRevert($this->blockTypeStorage, $this->revision, $this->logger('block_content'), $this->messenger(), $this->currentUser()->id(), $form_state);
    $form_state->setRedirect(
      'entity.block_content.version_history',
      ['block_content' => $this->revision->id()]
    );
  }

}
