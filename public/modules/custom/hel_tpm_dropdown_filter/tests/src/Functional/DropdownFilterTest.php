<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_dropdown_filter\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\better_exposed_filters\Traits\BetterExposedFiltersTrait;
use Drupal\Tests\hel_tpm_dropdown_filter\Traits\DropdownFilterTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the server rendered markup of the dropdown filter widget.
 */
#[Group('hel_tpm_dropdown_filter')]
#[RunTestsInSeparateProcesses]
final class DropdownFilterTest extends BrowserTestBase {

  use BetterExposedFiltersTrait;
  use DropdownFilterTestTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'breakpoint',
    'node',
    'views',
    'taxonomy',
    'better_exposed_filters',
    'bef_test',
    'hel_tpm_dropdown_filter',
  ];

  /**
   * Selector for the letters filter.
   */
  private const LETTERS = '[data-dropdown-filter="field_bef_letters_value"]';

  /**
   * Selector for the location filter.
   */
  private const LOCATION = '[data-dropdown-filter="field_bef_location_target_id"]';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    foreach (['One' => 'a', 'Two' => 'b', 'Three' => 'c'] as $title => $letter) {
      $this->createNode([
        'title' => "Page $title",
        'field_bef_letters' => $letter,
        'type' => 'bef_test',
      ]);
    }
  }

  /**
   * Tests that the filter renders as checkboxes inside a dropdown.
   */
  public function testMarkup(): void {
    $this->useDropdownFilter();
    $this->drupalGet('bef-test');
    $assert = $this->assertSession();

    $assert->elementNotExists('css', 'select[name^="field_bef_letters_value"]');
    foreach (['a', 'b', 'c', 'd'] as $letter) {
      $assert->elementExists('css', self::LETTERS . " details input[type=\"checkbox\"][name=\"field_bef_letters_value[$letter]\"][value=\"$letter\"]");
    }
    $assert->elementNotExists('css', self::LETTERS . ' input[checked]');

    $assert->elementTextEquals('css', self::LETTERS . ' .form-item__label', 'bef_letters (field_bef_letters)');
    // The description is used as the placeholder instead of being rendered.
    $assert->elementTextEquals('css', self::LETTERS . ' summary', 'Choose letters');
    $assert->elementNotExists('css', self::LETTERS . ' .description');
    $assert->elementNotExists('css', self::LETTERS . ' details.active');
    $assert->elementNotExists('css', self::LETTERS . '[data-dropdown-filter-single]');
    // Filters without a description get a default placeholder.
    $assert->elementTextEquals('css', self::LOCATION . ' summary', '- Any -');

    // Checkboxes elements are normally wrapped in a fieldset.
    $assert->elementNotExists('xpath', '//fieldset[contains(@class, "form-composite")]//*[@data-dropdown-filter="field_bef_letters_value"]');

    // Option labels are escaped.
    $assert->responseContains('Bumble &amp; the Bee');
    $assert->responseNotContains('Bumble & the Bee');
  }

  /**
   * Tests the selected state and filtering.
   */
  public function testSelection(): void {
    $this->useDropdownFilter();
    $this->drupalGet('bef-test', [
      'query' => ['field_bef_letters_value' => ['a' => 'a', 'b' => 'b']],
    ]);
    $assert = $this->assertSession();

    $assert->elementExists('css', self::LETTERS . ' input[name="field_bef_letters_value[a]"][checked]');
    $assert->elementExists('css', self::LETTERS . ' input[name="field_bef_letters_value[b]"][checked]');
    $assert->elementNotExists('css', self::LETTERS . ' input[name="field_bef_letters_value[c]"][checked]');
    $assert->elementTextEquals('css', self::LETTERS . ' summary', 'Aardvark +1');
    $assert->elementTextEquals('css', self::LETTERS . ' summary .trim-label', 'Aardvark');
    $assert->elementExists('css', self::LETTERS . ' details.active');

    $assert->pageTextContains('Page One');
    $assert->pageTextContains('Page Two');
    $assert->pageTextNotContains('Page Three');
  }

  /**
   * Tests URLs from the old select based widget, like ?field[0]=value.
   */
  public function testSelectionWithListQuery(): void {
    $this->useDropdownFilter();
    $this->drupalGet('bef-test', [
      'query' => ['field_bef_letters_value' => ['c']],
    ]);
    $assert = $this->assertSession();

    $assert->elementExists('css', self::LETTERS . ' input[name="field_bef_letters_value[c]"][checked]');
    $assert->elementTextEquals('css', self::LETTERS . ' summary', 'Le Chimpanzé');
    $assert->pageTextContains('Page Three');
    $assert->pageTextNotContains('Page One');
  }

  /**
   * Tests that the filter works without JavaScript.
   */
  public function testSubmit(): void {
    $this->useDropdownFilter();
    $this->drupalGet('bef-test');
    $this->submitForm(['field_bef_letters_value[b]' => TRUE], 'Apply');
    $assert = $this->assertSession();

    $assert->elementExists('css', self::LETTERS . ' input[name="field_bef_letters_value[b]"][checked]');
    $assert->pageTextContains('Page Two');
    $assert->pageTextNotContains('Page One');
    $assert->pageTextNotContains('Page Three');
  }

  /**
   * Tests the single selection setting.
   */
  public function testSingleSelect(): void {
    $this->useDropdownFilter(['single_select' => TRUE]);
    $this->drupalGet('bef-test');

    $this->assertSession()->elementExists('css', self::LETTERS . '[data-dropdown-filter-single]');
  }

  /**
   * Tests grouping taxonomy terms by parent term.
   */
  public function testTermGroups(): void {
    $this->useDropdownFilter();
    $this->drupalGet('bef-test');
    $assert = $this->assertSession();

    $legends = array_map(
      fn ($legend) => $legend->getText(),
      $this->getSession()->getPage()->findAll('css', self::LOCATION . ' .select-group > legend'),
    );
    sort($legends);
    // Groups are the parent terms that have children. Mexico has none.
    $this->assertSame([
      'Alberta',
      'British Columbia',
      'California',
      'Canada',
      'Oregon',
      'United States',
      'Washington',
    ], $legends);

    // Only child terms are selectable.
    $assert->elementNotExists('css', self::LOCATION . ' input[value="' . $this->getLocationTid('United States') . '"]');
    $assert->elementNotExists('css', self::LOCATION . ' input[value="' . $this->getLocationTid('Mexico') . '"]');

    $oregon = $assert->elementExists('xpath', '//*[@data-dropdown-filter="field_bef_location_target_id"]//fieldset[legend[normalize-space(.)="Oregon"]]');
    foreach (['Portland', 'Eugene'] as $city) {
      $tid = $this->getLocationTid($city);
      $assert->elementExists('css', "input[name=\"field_bef_location_target_id[$tid]\"]", $oregon);
    }
    // The group toggle is not submitted with the form.
    $toggle = $assert->elementExists('css', 'legend input[data-dropdown-filter-group]', $oregon);
    $this->assertFalse($toggle->hasAttribute('name'));
    $this->assertTrue($toggle->hasAttribute('data-bef-auto-submit-exclude'));
  }

  /**
   * Tests that single value filters are left as selects.
   */
  public function testSingleValueFilter(): void {
    $this->useDropdownFilter([], FALSE);
    $this->drupalGet('bef-test');

    $this->assertSession()->elementExists('css', 'select[name="field_bef_location_target_id"]');
    $this->assertSession()->elementNotExists('css', self::LOCATION);
  }

}
