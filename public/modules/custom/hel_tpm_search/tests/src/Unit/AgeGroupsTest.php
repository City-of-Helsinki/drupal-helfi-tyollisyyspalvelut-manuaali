<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_search\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\hel_tpm_search\Plugin\views\filter\AgeGroups;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests parsing the selected values of the age groups filter.
 */
#[Group('hel_tpm_search')]
final class AgeGroupsTest extends UnitTestCase {

  /**
   * Tests that only the predefined age ranges are accepted.
   *
   * @param mixed $value
   *   The filter value.
   * @param array $expected
   *   The expected ranges.
   */
  #[DataProvider('providerSelectedRanges')]
  public function testSelectedRanges(mixed $value, array $expected): void {
    $filter = new AgeGroups([], 'age_groups_filter', []);
    $filter->setStringTranslation($this->getStringTranslationStub());
    $filter->value = $value;

    $method = new \ReflectionMethod($filter, 'getSelectedRanges');
    $this->assertSame($expected, $method->invoke($filter));
  }

  /**
   * Data provider for testSelectedRanges().
   */
  public static function providerSelectedRanges(): array {
    return [
      'empty' => [[], []],
      'not an array' => ['16-29', []],
      'single' => [['16-29' => '16-29'], [[16, 29]]],
      'multiple' => [['16-29' => '16-29', '55-70' => '55-70'], [[16, 29], [55, 70]]],
      'list' => [['30-54'], [[30, 54]]],
      'duplicates' => [['16-29', '16-29'], [[16, 29]]],
      'unchecked checkboxes' => [['16-29' => '16-29', '30-54' => 0, '55-70' => 0], [[16, 29]]],
      'unknown range' => [['10-99'], []],
      'all' => [['all' => 'all'], []],
      'trailing newline' => [["16-29\n"], []],
      'nested array' => [[['16-29']], []],
      'other types' => [[NULL, TRUE, 1629, 16.29], []],
    ];
  }

}
