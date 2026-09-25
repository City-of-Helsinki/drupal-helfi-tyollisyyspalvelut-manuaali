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
  uses_menu_links: FALSE,
  // The views_view theme hook is provided by the views module, re-registering
  // it would point its template to this module.
  register_theme: FALSE,
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

    $exposed_input = $this->sanitizeExposedInput($this->view->getExposedInput());

    // The preset values travel in the view arguments, which the client sends
    // back on AJAX requests, so they may only target exposed filters.
    $filters = $this->sanitizeExposedInput($this->getDefaultFilters());
    if (!empty($filters)) {
      foreach ($this->view->filter as $filter) {
        if (!$filter->isExposed() || !empty($filter->options['is_grouped'])) {
          continue;
        }
        $identifier = $filter->options['expose']['identifier'];
        if (isset($filters[$identifier])) {
          $filter->value = $filters[$identifier];
        }
      }

      $exposed_input = array_merge($exposed_input, $filters);
    }

    // An empty exposed input would make the view read the raw request query
    // again, so only override it when something is left.
    if (!empty($exposed_input)) {
      $this->view->setExposedInput($exposed_input);
    }

    if ($this->usesExposed()) {
      $exposed_form = $this->getPlugin('exposed_form');
      $exposed_form->preExecute();
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
   * a JSON object with an 'exposed_embed' array are considered. If multiple
   * are found, the last one is returned.
   *
   * @return array
   *   An associative array representing the decoded filter settings.
   *   Returns an empty array if no valid filters are found.
   */
  private function getDefaultFilters(): array {
    $filters = [];

    foreach ($this->view->args ?? [] as $arg) {
      if (!is_string($arg) || !str_contains($arg, 'exposed_embed')) {
        continue;
      }
      $decoded = Json::decode($arg);
      if (is_array($decoded) && isset($decoded['exposed_embed']) && is_array($decoded['exposed_embed'])) {
        $filters = $decoded['exposed_embed'];
      }
    }

    return $filters;
  }

  /**
   * Removes unknown keys and duplicate values from the exposed input.
   *
   * The exposed input is used to build the pager links, so passing the raw
   * request query through lets arbitrary or repeated query parameters
   * propagate from page to page (and get crawled by search engines).
   *
   * @param array $input
   *   The exposed input.
   *
   * @return array
   *   The exposed input limited to the keys the view actually exposes, with
   *   duplicate values removed from multi-value filters.
   */
  protected function sanitizeExposedInput(array $input): array {
    $allowed = [
      'sort_by',
      'sort_order',
      'sort_bef_combine',
      'items_per_page',
      'offset',
    ];
    foreach ($this->view->filter as $filter) {
      if (!$filter->isExposed()) {
        continue;
      }
      if (!empty($filter->options['is_grouped'])) {
        $allowed[] = $filter->options['group_info']['identifier'];
        continue;
      }
      $allowed[] = $filter->options['expose']['identifier'];
      if (!empty($filter->options['expose']['use_operator'])) {
        $allowed[] = $filter->options['expose']['operator_id'];
      }
    }

    $input = array_intersect_key($input, array_flip(array_filter($allowed)));
    foreach ($input as $key => $value) {
      if (is_array($value) && array_is_list($value)) {
        $input[$key] = array_values(array_unique($value, SORT_REGULAR));
      }
    }
    return $input;
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
