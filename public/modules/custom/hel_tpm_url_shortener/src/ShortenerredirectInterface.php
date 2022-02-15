<?php

namespace Drupal\hel_tpm_url_shortener;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a shortenerredirect entity type.
 */
interface ShortenerredirectInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the shortenerredirect creation timestamp.
   *
   * @return int
   *   Creation timestamp of the shortenerredirect.
   */
  public function getCreatedTime();

  /**
   * Sets the shortenerredirect creation timestamp.
   *
   * @param int $timestamp
   *   The shortenerredirect creation timestamp.
   *
   * @return \Drupal\hel_tpm_url_shortener\ShortenerredirectInterface
   *   The called shortenerredirect entity.
   */
  public function setCreatedTime($timestamp);

}
