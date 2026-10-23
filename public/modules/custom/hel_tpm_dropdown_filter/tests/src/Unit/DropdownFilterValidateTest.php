<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_dropdown_filter\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use Drupal\hel_tpm_dropdown_filter\Plugin\better_exposed_filters\filter\DropdownFilter;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the submitted value of the dropdown filter widget.
 */
#[Group('hel_tpm_dropdown_filter')]
final class DropdownFilterValidateTest extends UnitTestCase {

  /**
   * Tests that unchecked options are removed from the value.
   */
  public function testUncheckedOptionsAreRemoved(): void {
    $form_state = new FormState();
    $form_state->setValues([
      'age' => [
        '16-29' => '16-29',
        '30-54' => 0,
        '55-70' => '55-70',
        '0' => '0',
      ],
    ]);
    $element = ['#parents' => ['age']];

    DropdownFilter::validateDropdown($element, $form_state);

    $this->assertSame([
      '16-29' => '16-29',
      '55-70' => '55-70',
      '0' => '0',
    ], $form_state->getValue('age'));
  }

  /**
   * Tests that nothing checked results in an empty value.
   */
  public function testNothingChecked(): void {
    $form_state = new FormState();
    $form_state->setValues(['age' => ['16-29' => 0, '30-54' => 0]]);
    $element = ['#parents' => ['age']];

    DropdownFilter::validateDropdown($element, $form_state);

    $this->assertSame([], $form_state->getValue('age'));
  }

}
