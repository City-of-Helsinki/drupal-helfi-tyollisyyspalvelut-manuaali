<?php

namespace Drupal\views_exposed_embed\Plugin\views\display;

use Drupal\Component\Serialization\Json;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsDisplay;
use Drupal\views\Plugin\views\display\Embed;

/**
 * Provides a Views display plugin for exposed embeds.
 *
 * This plugin allows the display to be embedded using the Views API. It
 * supports exposed forms and ensures integration with the Views system while
 * maintaining compatibility for embedding.
 */
#[ViewsDisplay(
  id: "exposed_embed",
  title: new TranslatableMarkup("Exposed Embed"),
  help: new TranslatableMarkup("Provide a display which can be embedded using the views api."),
  theme: "views_view",
  uses_menu_links: FALSE
)]

class ExposedEmbed extends Embed {

  /**
   * {@inheritdoc}
   */
  public function preExecute() {
    $this->view->setAjaxEnabled($this->ajaxEnabled());
    if ($this->isMoreEnabled() && !$this->useMoreAlways()) {
      $this->view->get_total_rows = TRUE;
    }
    $this->view->initHandlers();
    if ($this->usesExposed()) {
      /** @var \Drupal\views\Plugin\views\exposed_form\ExposedFormPluginInterface $exposed_form */
      $exposed_form = $this->getPlugin('exposed_form');
      $exposed_form->preExecute();
    }

    $filters = $this->getDefaultFilters();
    if (!empty($filters)) {
      $this->filter = $filters;
      foreach ($filters as $field => $value) {
        if (empty($this->view->filter[$field])) {
          continue;
        }
        $this->view->exposed_data[$field] = $value;
        $this->view->filter[$field]->value = $value;
      }
    }

    foreach ($this->extenders as $extender) {
      $extender->preExecute();
    }
  }

  /**
   * Retrieves the default filters applied to the view.
   *
   * This method processes the view arguments to extract and decode
   * filter definitions specified within them. Only arguments containing
   * the 'exposed_embed' string are considered. If multiple filters are found,
   * the first one is returned.
   *
   * @return array
   *   An associative array representing the decoded filter settings.
   *   Returns an empty array if no valid filters are found.
   */
  private function getDefaultFilters() {
    $filters = [];

    if (empty($this->view->args)) {
      return $filters;
    }

    $args = $this->view->args;
    foreach ($args as $arg) {
      if (strpos($arg, 'exposed_embed') === FALSE) {
        continue;
      }
      $filters = Json::decode($arg);
    }
    if (!empty($filters)) {
      $filters = reset($filters);
    }

    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function displaysExposed(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function usesExposedFormInBlock() {
    return TRUE;
  }

}
