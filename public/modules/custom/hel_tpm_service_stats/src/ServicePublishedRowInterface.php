<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_service_stats;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a service published row entity type.
 */
interface ServicePublishedRowInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
