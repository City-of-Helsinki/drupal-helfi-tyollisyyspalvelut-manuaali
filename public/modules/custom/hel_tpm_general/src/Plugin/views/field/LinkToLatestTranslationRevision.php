<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\Plugin\views\field;

use Drupal\views\Plugin\views\field\LinkToLatestRevision;
use Drupal\views\ResultRow;

/**
 * Provides Link to latest translation revision field handler.
 *
 * @ViewsField("link_to_latest_translation_revision")
 *
 * @DCG
 * The plugin needs to be assigned to a specific table column through
 * hook_views_data() or hook_views_data_alter().
 * Put the following code to hel_tpm_general.views.inc file.
 * @code
 * function foo_views_data_alter(array &$data): void {
 *   $data['node']['foo_example']['field'] = [
 *     'title' => t('Example'),
 *     'help' => t('Custom example field.'),
 *     'id' => 'foo_example',
 *   ];
 * }
 * @endcode
 */
final class LinkToLatestTranslationRevision extends LinkToLatestRevision {

  /**
   * Retrieves the URL information for a given result row.
   *
   * This method constructs a URL for the entity associated with the given
   * result row. If the language manager is multilingual, it retrieves the
   * entity translation and appends language-specific information.
   *
   * @param \Drupal\views\ResultRow $row
   *   The result row containing the data used to retrieve the entity.
   *
   * @return \Drupal\Core\Url
   *   The generated URL for the entity, with set template and absolute URL
   *   flag based on the provided options.
   */
  protected function getUrlInfo(ResultRow $row) {
    $entity = $this->getEntity($row);
    $template = 'latest-version';
    if ($this->languageManager->isMultilingual()) {
      $entity = $this->getEntityTranslationByRelationship($entity, $row);
      $this->addLangcode($row);
    }
    return $entity->toUrl($template)->setAbsolute($this->options['absolute']);
  }

  /**
   * Checks if the URL access is allowed for the specified entity.
   *
   * This method determines if the current user has access to edit the given
   * entity, taking into account possible translations in a multilingual setup.
   *
   * @param \ResultRow $row
   *   The result row that contains the entity to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result indicating whether the operation is permitted.
   */
  protected function checkUrlAccess(ResultRow $row) {
    $entity = $this->getEntity($row);
    if ($this->languageManager->isMultilingual()) {
      $entity = $this->getEntityTranslationByRelationship($entity, $row);
    }
    // Check access on the translated entity directly instead of using the
    // named route check, which loads the default-language entity from route
    // parameters and may incorrectly return forbidden for translations that
    // have a pending revision only in their language.
    return $entity->access('edit', $this->currentUser(), TRUE);
  }

}
