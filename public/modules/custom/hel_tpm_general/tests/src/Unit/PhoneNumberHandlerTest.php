<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_general\Unit;

use Drupal\hel_tpm_general\PhoneNumberHandler;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the phone number handler.
 */
#[CoversClass(PhoneNumberHandler::class)]
#[Group('hel_tpm_general')]
final class PhoneNumberHandlerTest extends UnitTestCase {

  /**
   * Numbers and their tel: URIs.
   */
  public static function telUriProvider(): array {
    return [
      'spaces are removed' => ['040 123 4567', 'tel:0401234567'],
      'country code' => ['+358 40 123 4567', 'tel:%2B358401234567'],
      'short number' => ['112', 'tel:1-12'],
      'five digits' => ['10023', 'tel:1-0023'],
      'empty' => ['', NULL],
      'whitespace' => ['   ', NULL],
    ];
  }

  /**
   * Tests building tel: URIs.
   */
  #[DataProvider('telUriProvider')]
  public function testToTelUri(string $number, ?string $expected): void {
    $this->assertSame($expected, (new PhoneNumberHandler())->toTelUri($number));
  }

  /**
   * Numbers, and their country codes and national parts with default +358.
   */
  public static function splitNumberProvider(): array {
    return [
      'leading zero' => ['040 123 3232', ['+358', '40 123 3232']],
      'country code' => ['+358 40 123 3232', ['+358', '40 123 3232']],
      'country code without space' => ['+358401233232', ['+358', '401233232']],
      'international prefix' => ['00358 40 123 3232', ['+358', '40 123 3232']],
      'country code and trunk prefix' => ['+358 (0)40 123 3232', ['+358', '40 123 3232']],
      'country code and zero' => ['+358 040 123 3232', ['+358', '40 123 3232']],
      'national part only' => ['40 123 3232', ['+358', '40 123 3232']],
      'landline' => ['09 310 12345', ['+358', '9 310 12345']],
      'surrounding whitespace' => ['  040 123 3232 ', ['+358', '40 123 3232']],
      'other country code' => ['+46 8 123 456', ['+46', '8 123 456']],
      'other country code with dash' => ['+46-8-123 456', ['+46', '8-123 456']],
      'other international prefix' => ['0046 8 123 456', ['+46', '8 123 456']],
      'empty' => ['', ['+358', '']],
    ];
  }

  /**
   * Tests splitting numbers into the country code and the national part.
   */
  #[DataProvider('splitNumberProvider')]
  public function testSplitNumber(string $number, array $expected): void {
    $this->assertSame($expected, (new PhoneNumberHandler())->splitNumber($number, '+358'));
  }

  /**
   * Tests that the given default country code is used.
   */
  public function testSplitNumberDefaultCountryCode(): void {
    $handler = new PhoneNumberHandler();
    $this->assertSame(['+46', '8 123 456'], $handler->splitNumber('08 123 456', '+46'));
    $this->assertSame(['+46', '8123456'], $handler->splitNumber('+468123456', '+46'));
  }

  /**
   * Tests the country code pattern.
   */
  public function testCountryCodePattern(): void {
    foreach (['+1', '+358', '+1242'] as $valid) {
      $this->assertMatchesRegularExpression(PhoneNumberHandler::COUNTRY_CODE_PATTERN, $valid);
    }
    foreach (['358', '+', '+12345', '+35a', ' +358'] as $invalid) {
      $this->assertDoesNotMatchRegularExpression(PhoneNumberHandler::COUNTRY_CODE_PATTERN, $invalid);
    }
  }

}
