<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\link\AttributeXss;
use Drupal\link\LinkItemInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;

/**
 * Plugin implementation of the 'Link External New Tab' formatter.
 */
#[FieldFormatter(
  id: 'link_external_new_tab',
  label: new TranslatableMarkup('Link External New Tab'),
  field_types: [
    'link',
  ],
)]
final class LinkExternalNewTabFormatter extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  protected function buildUrl(LinkItemInterface $item) {
    try {
      $url = $item->getUrl();
    }
    catch (\InvalidArgumentException $e) {
      // @todo Add logging here in https://www.drupal.org/project/drupal/issues/3348020
      $url = Url::fromRoute('<none>');
    }

    $settings = $this->getSettings();
    $options = $item->options;
    $options += $url->getOptions();

    // Add optional 'rel' attribute to link options.
    if (!empty($settings['rel'])) {
      $options['attributes']['rel'] = $settings['rel'];
    }
    // Add target _blank only for external urls.
    if (!empty($settings['target']) && $url->isExternal()) {
      $options['attributes']['target'] = $settings['target'];
      $options['attributes']['rel'][] = 'noopener';
      $options['attributes']['class'][] = 'ext-link';
    }

    if (!empty($options['attributes'])) {
      $options['attributes'] = AttributeXss::sanitizeAttributes($options['attributes']);
    }

    $url->setOptions($options);
    return $url;
  }

}
