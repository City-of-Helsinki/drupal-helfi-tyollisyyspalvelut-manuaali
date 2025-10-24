<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a text filter to convert external links to open in a new tab.
 *
 * This filter scans anchor links in the provided HTML text. It modifies their
 * attributes based on whether the links point to external or internal
 * destinations. External links are updated to open in a new browser tab, while
 * internal links may have their URLs changed to relative paths.
 */
#[Filter(
  id: "external_links_to_new_tab",
  title: new TranslatableMarkup("Convert External links to open in new tab"),
  type: FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
  description: new TranslatableMarkup("Convert external links to open in new tab and change absolute internal links to relative."),
  settings: [
    "internal_hosts" => "",
  ]
)]
final class ExternalLinksToNewTab extends FilterBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new ExternalLinksToNewTab instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly RequestStack $requestStack,
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
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['internal_hosts'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Internal hosts'),
      '#default_value' => $this->settings['internal_hosts'],
      '#description' => $this->t('List of domains considered internal. One host per row.'),
    ];
    return $form;
  }

  /**
   * Processes the given text, updating anchor links based on their attributes.
   *
   * If a link is determined to be external, it adds a 'target' attribute with
   * the value '_blank'. For internal links, it modifies the 'href' attribute
   * to remove protocol and domain segments.
   *
   * @param string $text
   *   The text to be processed, expected to contain HTML content.
   * @param string $langcode
   *   The language code indicating the language of the text.
   *
   * @return \Drupal\filter\FilterProcessResult
   *   The result of the text processing, encapsulated in a FilterProcessResult
   *   object.
   */
  public function process($text, $langcode): FilterProcessResult {
    $dom = Html::load($text);
    foreach ($dom->getElementsByTagName('a') as $link) {
      $href = $link->getAttribute('href');
      if (!$this->validateUrl($href)) {
        continue;
      }
      if ($this->isExternal($href)) {
        $this->setExternalLinkAttributes($link, $dom);
      }
      else {
        // Convert absolute internal links to relative.
        $link->setAttribute('href', preg_replace('/^(?:https?:\/\/)?(?:[^@\/\n]+@)?(?:www\.)?([^:\/\n]+)(:\d+)?/i', '', $href));
      }
    }
    return new FilterProcessResult(Html::serialize($dom));
  }

  /**
   * Validates whether the given URL uses an allowed scheme.
   *
   * @param string $href
   *   The URL to be validated.
   *
   * @return bool
   *   TRUE if the URL uses an allowed scheme, FALSE otherwise.
   */
  protected function validateUrl($href) {
    $schemes = ['http', 'https'];
    $url = parse_url($href);
    return in_array($url['scheme'], $schemes);
  }

  /**
   * Sets attributes for external links to open in a new tab with.
   *
   * @param \DOMElement $link
   *   The DOM element representing the link to modify.
   * @param \DOMDocument $dom
   *   The DOMDocument instance used to create new elements.
   *
   * @return void
   *   This method does not return a value.
   */
  protected function setExternalLinkAttributes(&$link, $dom) {
    $link->setAttribute('target', '_blank');
    $link->setAttribute('class', 'ext-link');
    $link->setAttribute('rel', 'noopener');
    $accessible_label = $dom->createElement(
      'span',
      $this->t('external link, opens in a new tab')->render()
    );
    $accessible_label->setAttribute('class', 'visually-hidden');
    $link->appendChild($accessible_label);
  }

  /**
   * Determines if a given URL is external.
   *
   * @param string $href
   *   The URL to evaluate.
   *
   * @return bool
   *   TRUE if the URL is external, FALSE otherwise.
   */
  protected function isExternal($href) {
    $hosts = $this->getSettingInternlHosts();
    $hosts[] = $this->requestStack->getMainRequest()->getHttpHost();
    $url = parse_url($href);
    if (isset($url['host']) && in_array(preg_replace('/^www\./', '', mb_strtolower($url['host'])), $hosts)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Retrieves the internal hosts setting.
   *
   * @return array
   *   An array of internal hosts defined in the settings. If no internal hosts
   *   are set, an empty array is returned.
   */
  protected function getSettingInternlHosts() {
    if (empty($this->settings['internal_hosts'])) {
      return [];
    }
    $hosts = Xss::filter($this->settings['internal_hosts']);
    return explode("\r\n", $hosts);

  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE): string {
    return (string) $this->t('Converts links to external sources to always open in new tab.');
  }

}
