<?php

declare(strict_types=1);

namespace Drupal\views_exposed_embed_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the views_exposed_embed_test module.
 */
class ViewsExposedEmbedTestHooks {

  /**
   * Implements hook_views_data_alter().
   *
   * The entity_test bundle field is a plain string filter, turn it into a
   * multi-value select of the bundles.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    $data['entity_test']['type']['filter'] = [
      'id' => 'in_operator',
      'options callback' => [static::class, 'bundleOptions'],
    ] + ($data['entity_test']['type']['filter'] ?? []);
  }

  /**
   * Returns the entity_test bundles as filter options.
   *
   * @return array
   *   Bundle labels keyed by bundle name.
   */
  public static function bundleOptions(): array {
    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('entity_test');
    return array_map(static fn(array $info): string => (string) $info['label'], $bundles);
  }

}
