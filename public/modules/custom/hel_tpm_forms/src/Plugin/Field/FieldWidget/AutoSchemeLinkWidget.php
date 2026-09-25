<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_forms\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Link widget adding the https scheme to external links missing one.
 */
#[FieldWidget(
  id: 'auto_scheme_link_widget',
  label: new TranslatableMarkup('Link with automatic https'),
  field_types: ['link'],
)]
class AutoSchemeLinkWidget extends LinkWidget {

  /**
   * {@inheritdoc}
   *
   * Adds the https scheme to external links entered without one, e.g.
   * "example.org" becomes "https://example.org".
   */
  protected static function getUserEnteredStringAsUri($string) {
    $string = trim((string) $string);
    if (static::isSchemelessExternalUrl($string)) {
      $string = 'https://' . $string;
    }
    return parent::getUserEnteredStringAsUri($string);
  }

  /**
   * Checks if the given string looks like an external URL without a scheme.
   *
   * @param string $string
   *   The user entered string.
   *
   * @return bool
   *   TRUE if the string starts with a domain name and has no scheme, FALSE
   *   otherwise.
   */
  protected static function isSchemelessExternalUrl(string $string): bool {
    // Pattern checks first domain label, any number of further labels, the
    // top-level domain label, optional port, and optional rest starting with
    // '/', '?' or '#'.
    return (bool) preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}(:\d+)?([\/?#]\S*)?$/i', $string);
  }

}
