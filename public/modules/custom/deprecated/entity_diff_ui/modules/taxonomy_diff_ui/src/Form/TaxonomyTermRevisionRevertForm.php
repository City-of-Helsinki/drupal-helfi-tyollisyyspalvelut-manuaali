<?php

namespace Drupal\taxonomy_diff_ui\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_diff_ui\Utility\RevisionOperationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a taxonomy term revision.
 *
 * @internal
 */
class TaxonomyTermRevisionRevertForm extends ConfirmFormBase {

  use RevisionOperationTrait;

  /**
   * The taxonomy term revision.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $revision;

  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $termStorage;

  /**
   * The vocabulary storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $vocabularyStorage;

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
   * Constructs a new TaxonomyTermRevisionRevertForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $taxonomy_term_storage
   *   The taxonomy term storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $vocabulary_storage
   *   The vocabulary storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityStorageInterface $taxonomy_term_storage, EntityStorageInterface $vocabulary_storage, DateFormatterInterface $date_formatter, TimeInterface $time) {
    $this->termStorage = $taxonomy_term_storage;
    $this->vocabularyStorage = $vocabulary_storage;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('taxonomy_term'),
      $container->get('entity_type.manager')->getStorage('taxonomy_vocabulary'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_term_revision_revert_confirm';
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
    return new Url('entity.taxonomy_term.version_history', ['taxonomy_term' => $this->revision->id()]);
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
  public function buildForm(array $form, FormStateInterface $form_state, $taxonomy_term_revision = NULL) {
    $this->revision = $this->termStorage->loadRevision($taxonomy_term_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->revisionRevert($this->vocabularyStorage, $this->revision, $this->logger('taxonomy'), $this->messenger(), $this->currentUser()->id(), $form_state);
    $form_state->setRedirect(
      'entity.taxonomy_term.version_history',
      ['taxonomy_term' => $this->revision->id()]
    );
  }

}
