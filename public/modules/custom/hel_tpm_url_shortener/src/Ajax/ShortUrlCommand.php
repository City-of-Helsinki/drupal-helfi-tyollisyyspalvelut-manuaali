<?php

namespace Drupal\hel_tpm_url_shortener\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Represents a command to handle and render a shortened URL.
 */
class ShortUrlCommand implements CommandInterface {

  /**
   * A variable to hold the shortened URL.
   */
  protected string $shortUrl;

  /**
   * Constructs an instance with the provided short URL.
   *
   * @param string $shortUrl
   *   The shortened URL to be assigned to the instance.
   *
   * @return void
   *   Void.
   */
  public function __construct(string $shortUrl) {
    $this->shortUrl = $shortUrl;
  }

  /**
   * Renders a response containing a command and its associated short URL.
   *
   * @return array
   *   An associative array with 'command' specifying the command type
   *   and 'shortUrl' providing the corresponding shortened URL.
   */
  public function render() {
    return [
      'command' => 'shortUrlCommand',
      'shortUrl' => $this->shortUrl,
    ];
  }

}
