<?php

namespace Drupal\hel_tpm_contact_info;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a contact info entity type.
 */
interface ContactInfoInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the contact info title.
   *
   * @return string
   *   Title of the contact info.
   */
  public function getTitle();

  /**
   * Sets the contact info title.
   *
   * @param string $title
   *   The contact info title.
   *
   * @return \Drupal\hel_tpm_contact_info\ContactInfoInterface
   *   The called contact info entity.
   */
  public function setTitle($title);

  /**
   * Gets the contact info creation timestamp.
   *
   * @return int
   *   Creation timestamp of the contact info.
   */
  public function getCreatedTime();

  /**
   * Sets the contact info creation timestamp.
   *
   * @param int $timestamp
   *   The contact info creation timestamp.
   *
   * @return \Drupal\hel_tpm_contact_info\ContactInfoInterface
   *   The called contact info entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the contact info status.
   *
   * @return bool
   *   TRUE if the contact info is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the contact info status.
   *
   * @param bool $status
   *   TRUE to enable this contact info, FALSE to disable.
   *
   * @return \Drupal\hel_tpm_contact_info\ContactInfoInterface
   *   The called contact info entity.
   */
  public function setStatus($status);

}
