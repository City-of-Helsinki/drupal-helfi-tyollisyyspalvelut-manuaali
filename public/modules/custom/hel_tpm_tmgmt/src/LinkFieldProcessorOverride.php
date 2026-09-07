<?php

namespace Drupal\hel_tpm_tmgmt;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;
use Drupal\tmgmt_content\DefaultFieldProcessor;

/**
 * Extends the DefaultFieldProcessor to handle link fields specifically.
 */
class LinkFieldProcessorOverride extends DefaultFieldProcessor {

  /**
   * {@inheritdoc}
   */
  public function extractTranslatableData(FieldItemListInterface $field) {
    $data = parent::extractTranslatableData($field);
    foreach (Element::children($data) as $key) {
      if (!empty($data[$key]['uri']['#translate'])) {
        $data[$key]['uri']['#translate'] = UrlHelper::isExternal($data[$key]['uri']['#text']);

      }
    }
    return $data;
  }

}
