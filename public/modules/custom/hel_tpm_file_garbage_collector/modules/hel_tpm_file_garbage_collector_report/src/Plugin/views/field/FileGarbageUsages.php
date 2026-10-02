<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_file_garbage_collector_report\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the entities and revisions that used a reported file.
 *
 * Each usage is rendered as a single inline sentence, e.g. "Service title
 * (Content 12, revisions: 34, 35)", with the entity and the revisions linked
 * when they have a page.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("hel_tpm_file_garbage_collector_report_usages")]
final class FileGarbageUsages extends FieldPluginBase {

  /**
   * Constructs a FileGarbageUsages object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $usages = Json::decode((string) $this->getValue($values)) ?: [];
    if (empty($usages)) {
      return '';
    }

    $metadata = new BubbleableMetadata();
    $items = [];
    foreach ($usages as $usage) {
      $items[] = ['#markup' => $this->describeUsage($usage, $metadata)];
    }
    $build = [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
    $metadata->applyTo($build);
    return $build;
  }

  /**
   * Describes an entity that used the file.
   *
   * @param array $usage
   *   The usage from the report entry.
   * @param \Drupal\Core\Render\BubbleableMetadata $metadata
   *   Collects the cacheability of the generated links.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   E.g. "Service title (Content 12, revisions: 34, 35)".
   */
  private function describeUsage(array $usage, BubbleableMetadata $metadata): MarkupInterface {
    $entity_type_id = $usage['entity_type'];
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
    $label = $usage['label'] ?? $this->t('@type @id', [
      '@type' => $entity_type_id,
      '@id' => $usage['entity_id'],
    ]);

    $label_markup = $entity_type?->hasLinkTemplate('canonical')
      ? $this->link($label, Url::fromRoute('entity.' . $entity_type_id . '.canonical', [$entity_type_id => $usage['entity_id']]), $metadata)
      : $label;

    $revisions = [];
    foreach ($usage['revision_ids'] ?? [] as $revision_id) {
      $revisions[] = $this->describeRevision($usage, $label, (int) $revision_id, $metadata);
    }

    $description = $this->t('@label (@type @id, revisions: @revisions)', [
      '@label' => $label_markup,
      '@type' => $entity_type ? $entity_type->getLabel() : $entity_type_id,
      '@id' => $usage['entity_id'],
      // Each revision is either escaped text or a generated link, so the
      // joined list is safe markup.
      '@revisions' => $revisions ? Markup::create(implode(', ', $revisions)) : '-',
    ]);

    if (!empty($usage['host'])) {
      $description = $this->t('@usage, used through @host', [
        '@usage' => $description,
        '@host' => $this->describeUsage($usage['host'], $metadata),
      ]);
    }
    return $description;
  }

  /**
   * Describes a revision ID, linked to the revision when it has a page.
   *
   * @param array $usage
   *   The usage from the report entry.
   * @param \Drupal\Component\Render\MarkupInterface|string $label
   *   The label of the entity.
   * @param int $revision_id
   *   The revision ID.
   * @param \Drupal\Core\Render\BubbleableMetadata $metadata
   *   Collects the cacheability of the generated links.
   *
   * @return string
   *   The revision ID as escaped text or a generated link.
   */
  private function describeRevision(array $usage, MarkupInterface|string $label, int $revision_id, BubbleableMetadata $metadata): string {
    $entity_type_id = $usage['entity_type'];
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
    if (!$entity_type?->hasLinkTemplate('revision')) {
      return (string) $revision_id;
    }

    $url = Url::fromRoute('entity.' . $entity_type_id . '.revision', [
      $entity_type_id => $usage['entity_id'],
      $entity_type_id . '_revision' => $revision_id,
    ], [
      'attributes' => [
        'aria-label' => (string) $this->t('Revision @revision of @label', [
          '@revision' => $revision_id,
          '@label' => $label,
        ]),
      ],
    ]);
    return (string) $this->link((string) $revision_id, $url, $metadata);
  }

  /**
   * Generates a link and collects its cacheability.
   *
   * @param \Drupal\Component\Render\MarkupInterface|string $text
   *   The link text.
   * @param \Drupal\Core\Url $url
   *   The URL.
   * @param \Drupal\Core\Render\BubbleableMetadata $metadata
   *   Collects the cacheability of the generated link.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The link markup.
   */
  private function link(MarkupInterface|string $text, Url $url, BubbleableMetadata $metadata): MarkupInterface {
    $link = Link::fromTextAndUrl($text, $url)->toString();
    $metadata->addCacheableDependency($link);
    return $link;
  }

}
