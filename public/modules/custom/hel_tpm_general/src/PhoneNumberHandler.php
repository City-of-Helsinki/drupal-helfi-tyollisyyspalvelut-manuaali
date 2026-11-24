<?php

declare(strict_types=1);

namespace Drupal\hel_tpm_general;

/**
 * Handles phone numbers: country codes and tel: URIs.
 *
 * The tel: URIs are built the same way as in core's telephone link formatter.
 *
 * @see \Drupal\telephone\Plugin\Field\FieldFormatter\TelephoneLinkFormatter
 */
final class PhoneNumberHandler {

  /**
   * Pattern of a valid country code, e.g. "+358".
   */
  public const COUNTRY_CODE_PATTERN = '/^\+[0-9]{1,4}$/';

  /**
   * Splits a number into the country code and the national part.
   *
   * The national part is returned without the trunk prefix (leading zero).
   * E.g. with the default country code "+358":
   * - "040 123 4567" gives ["+358", "40 123 4567"].
   * - "+358 (0)40 123 4567" gives ["+358", "40 123 4567"].
   * - "+46 8 123 456" and "0046 8 123 456" give ["+46", "8 123 456"].
   *
   * A country code other than the default one is only recognized when it is
   * followed by a space or a dash, because without it the end of the country
   * code can't be known.
   *
   * @param string $number
   *   The phone number.
   * @param string $default_country_code
   *   The country code used when the number has none, e.g. "+358".
   *
   * @return array{0: string, 1: string}
   *   The country code and the national part of the number.
   */
  public function splitNumber(string $number, string $default_country_code): array {
    $number = trim($number);
    $default_digits = preg_quote(ltrim($default_country_code, '+'), '/');

    if (preg_match('/^(?:\+|00)([0-9]{1,4})[\s-]+(.*)$/s', $number, $matches)) {
      return ['+' . $matches[1], $this->stripTrunkPrefix($matches[2])];
    }
    if ($default_digits !== '' && preg_match('/^(?:\+|00)' . $default_digits . '(.*)$/s', $number, $matches)) {
      return [$default_country_code, $this->stripTrunkPrefix($matches[1])];
    }
    return [$default_country_code, $this->stripTrunkPrefix($number)];
  }

  /**
   * Gets the tel: URI of the number, e.g. "tel:0401234567".
   *
   * @param string $number
   *   The phone number.
   *
   * @return string|null
   *   The URI, or NULL if the number is empty.
   */
  public function toTelUri(string $number): ?string {
    // Whitespace is stripped, so that digits are counted correctly.
    $number = preg_replace('/\s+/', '', $number);
    if ($number === '') {
      return NULL;
    }
    // If the number has 5 or fewer digits, parse_url() thinks it is a port
    // number. A dash (a visual separator in RFC 3966) after the first digit
    // avoids that. See https://bugs.php.net/bug.php?id=70588.
    if (strlen($number) <= 5) {
      $number = substr_replace($number, '-', 1, 0);
    }
    return 'tel:' . rawurlencode($number);
  }

  /**
   * Removes the trunk prefix, written as "(0)" or as a leading zero.
   */
  private function stripTrunkPrefix(string $number): string {
    return trim(preg_replace('/^\s*(?:\(0\)|0)\s*/', '', $number));
  }

}
