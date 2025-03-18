<?php

namespace Drupal\views_exposed_embed\Plugin\views\display;

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
