<?php

namespace Drupal\taxonomy_diff_ui\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_diff_ui\Utility\RevisionOperationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a taxonomy term revision.
 *
 * @internal
 */
class TaxonomyTermRevisionDeleteForm extends ConfirmFormBase {

  use RevisionOperationTrait;

  /**
   * The taxonomy term revision.
   *
   * @var \Drupal\Core\Entity\EditorialContentEntityBase
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
   * The database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new TaxonomyTermRevisionDeleteForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $term_storage
   *   The taxonomy term storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $vocabulary_storage
   *   The vocabulary storage.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(EntityStorageInterface $term_storage, EntityStorageInterface $vocabulary_storage, Connection $connection, DateFormatterInterface $date_formatter) {
    $this->termStorage = $term_storage;
    $this->vocabularyStorage = $vocabulary_storage;
    $this->connection = $connection;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_type_manager = $container->get('entity_type.manager');
    return new TaxonomyTermRevisionDeleteForm(
      $entity_type_manager->getStorage('taxonomy_term'),
      $entity_type_manager->getStorage('taxonomy_vocabulary'),
      $container->get('database'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_term_revision_delete_confirm';
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
    return new Url('entity.taxonomy_term.version_history', ['taxonomy_term' => $this->revision->id()]);
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
  public function buildForm(array $form, FormStateInterface $form_state, $taxonomy_term_revision = NULL) {
    $this->revision = $this->termStorage->loadRevision($taxonomy_term_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->revisionDelete($this->termStorage, $this->vocabularyStorage, $this->revision, $this->logger('taxonomy'), $this->messenger());
    $form_state->setRedirect(
        'entity.taxonomy_term.canonical',
        ['taxonomy_term' => $this->revision->id()]
        );
    if ($this->connection->query('SELECT COUNT(DISTINCT revision_id) FROM {taxonomy_term_field_revision} WHERE tid = :tid', [':tid' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.taxonomy_term.version_history',
        ['taxonomy_term' => $this->revision->id()]
      );
    }
  }

}
