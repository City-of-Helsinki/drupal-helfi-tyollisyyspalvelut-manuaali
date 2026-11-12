<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hel_tpm_general\PhoneNumberHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Telephone with title' formatter.
 *
 * Renders the title (if any) and the telephone number as a tel: link.
 */
#[FieldFormatter(
  id: 'hel_tpm_telephone_with_title_link',
  label: new TranslatableMarkup('Telephone link with title'),
  field_types: ['hel_tpm_telephone_with_title'],
)]
class TelephoneWithTitleFormatter extends FormatterBase {

  /**
   * The phone number handler.
   */
  protected PhoneNumberHandler $phoneNumberHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->phoneNumberHandler = $container->get('hel_tpm_general.phone_number_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    /** @var \Drupal\hel_tpm_general\Plugin\Field\FieldType\TelephoneWithTitleItem $item */
    foreach ($items as $delta => $item) {
      $number = (string) $item->value;
      $elements[$delta] = [
        '#theme' => 'hel_tpm_general_telephone_with_title',
        '#title' => (string) $item->title,
        '#number' => $number,
        '#url' => $this->phoneNumberHandler->toTelUri($number),
      ];
    }

    return $elements;
  }

}
