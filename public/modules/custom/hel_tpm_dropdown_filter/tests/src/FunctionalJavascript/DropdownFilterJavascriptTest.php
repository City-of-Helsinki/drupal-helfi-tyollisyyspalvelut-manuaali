<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_dropdown_filter\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\better_exposed_filters\Traits\BetterExposedFiltersTrait;
use Drupal\Tests\hel_tpm_dropdown_filter\Traits\DropdownFilterTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the dropdown filter behavior with AJAX and auto-submit.
 */
#[Group('hel_tpm_dropdown_filter')]
#[RunTestsInSeparateProcesses]
final class DropdownFilterJavascriptTest extends WebDriverTestBase {

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
   * WebDriver key codes.
   */
  private const KEY_ENTER = "\u{E007}";
  private const KEY_ESCAPE = "\u{E00C}";

  /**
   * WebDriver key codes for the arrow, Home and End keys.
   */
  private const KEY_UP = "\u{E013}";
  private const KEY_DOWN = "\u{E015}";
  private const KEY_HOME = "\u{E011}";
  private const KEY_END = "\u{E010}";

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createNode([
      'title' => 'Page One',
      'field_bef_letters' => 'a',
      'field_bef_location' => $this->getLocationTid('Portland'),
      'type' => 'bef_test',
    ]);
    $this->createNode([
      'title' => 'Page Two',
      'field_bef_letters' => 'b',
      'field_bef_location' => $this->getLocationTid('Vancouver'),
      'type' => 'bef_test',
    ]);

    \Drupal::configFactory()->getEditable('views.view.bef_test')
      ->set('display.default.display_options.use_ajax', TRUE)
      ->save();
    $this->setBetterExposedOptions(Views::getView('bef_test'), [
      'general' => ['autosubmit' => TRUE],
    ]);
  }

  /**
   * Tests that the dropdown closes after a selection.
   */
  public function testCloseAfterSelection(): void {
    $this->useDropdownFilter();
    $this->drupalGet('bef-test');
    $assert = $this->assertSession();

    $this->click(self::LETTERS . ' summary');
    $this->assertTrue($this->isOpen(self::LETTERS));

    $this->clickOption(self::LETTERS, 'Aardvark');
    // The dropdown closes before the AJAX request finishes.
    $this->assertFalse($this->isOpen(self::LETTERS));
    $assert->elementTextEquals('css', self::LETTERS . ' summary', 'Aardvark');

    $assert->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Page One');
    $assert->pageTextNotContains('Page Two');
    $assert->elementTextEquals('css', self::LETTERS . ' summary', 'Aardvark');
    $assert->elementExists('css', self::LETTERS . ' details.active');
    // Focus is moved back to the same filter after the form is replaced.
    $this->assertTrue($this->isFocused(self::LETTERS . ' summary'));

    // Further options can be added by opening the dropdown again.
    $this->click(self::LETTERS . ' summary');
    $this->clickOption(self::LETTERS, 'Bumble & the Bee');
    $assert->assertWaitOnAjaxRequest();
    $assert->elementTextEquals('css', self::LETTERS . ' summary', 'Aardvark +1');
    $assert->pageTextContains('Page One');
    $assert->pageTextContains('Page Two');
  }

  /**
   * Tests the single selection setting.
   */
  public function testSingleSelect(): void {
    $this->useDropdownFilter(['single_select' => TRUE]);
    $this->drupalGet('bef-test');
    $assert = $this->assertSession();

    $this->click(self::LETTERS . ' summary');
    $this->clickOption(self::LETTERS, 'Aardvark');
    $assert->assertWaitOnAjaxRequest();

    $this->click(self::LETTERS . ' summary');
    $this->clickOption(self::LETTERS, 'Bumble & the Bee');
    $this->assertSame(['b'], $this->getCheckedValues(self::LETTERS));
    $assert->assertWaitOnAjaxRequest();
    $this->assertSame(['b'], $this->getCheckedValues(self::LETTERS));
    $assert->pageTextContains('Page Two');
    $assert->pageTextNotContains('Page One');

    // Clicking the selected option clears the selection.
    $this->click(self::LETTERS . ' summary');
    $this->clickOption(self::LETTERS, 'Bumble & the Bee');
    $assert->assertWaitOnAjaxRequest();
    $this->assertSame([], $this->getCheckedValues(self::LETTERS));
    $assert->pageTextContains('Page One');
  }

  /**
   * Tests selecting all options of a group.
   */
  public function testGroupSelection(): void {
    $this->useDropdownFilter();
    $this->drupalGet('bef-test');
    $assert = $this->assertSession();
    $oregon = [$this->getLocationTid('Portland'), $this->getLocationTid('Eugene')];

    $this->click(self::LOCATION . ' summary');
    $this->getGroupLabel('Oregon')->click();
    $this->assertFalse($this->isOpen(self::LOCATION));
    $assert->assertWaitOnAjaxRequest();

    $this->assertEqualsCanonicalizing($oregon, $this->getCheckedValues(self::LOCATION));
    $this->assertTrue($this->getGroupToggle('Oregon')->isChecked());
    $assert->pageTextContains('Page One');
    $assert->pageTextNotContains('Page Two');

    // Clicking the group again deselects its options.
    $this->click(self::LOCATION . ' summary');
    $this->getGroupLabel('Oregon')->click();
    $assert->assertWaitOnAjaxRequest();
    $this->assertSame([], $this->getCheckedValues(self::LOCATION));
    $this->assertFalse($this->getGroupToggle('Oregon')->isChecked());
  }

  /**
   * Tests that a partially selected group is shown as indeterminate.
   */
  public function testGroupIndeterminateState(): void {
    $this->useDropdownFilter();
    $this->drupalGet('bef-test', [
      'query' => ['field_bef_location_target_id' => [$this->getLocationTid('Portland')]],
    ]);

    $state = $this->getSession()->evaluateScript('(function () {
      const toggle = Array.from(document.querySelectorAll(\'' . self::LOCATION . ' legend\'))
        .find((legend) => legend.textContent.trim() === "Oregon")
        .querySelector("input");
      return [toggle.checked, toggle.indeterminate];
    })()');
    $this->assertSame([FALSE, TRUE], $state);
  }

  /**
   * Tests keyboard use.
   */
  public function testKeyboard(): void {
    $this->useDropdownFilter();
    $this->drupalGet('bef-test');
    $assert = $this->assertSession();

    // Enter opens the dropdown and Escape closes it.
    $this->pressKey(self::LETTERS . ' summary', self::KEY_ENTER);
    $this->assertTrue($this->isOpen(self::LETTERS));
    $this->pressKey(self::LETTERS . ' input[value="a"]', self::KEY_ESCAPE);
    $this->assertFalse($this->isOpen(self::LETTERS));
    $this->assertTrue($this->isFocused(self::LETTERS . ' summary'));

    // Enter toggles an option.
    $this->pressKey(self::LETTERS . ' summary', self::KEY_ENTER);
    $this->pressKey(self::LETTERS . ' input[value="a"]', self::KEY_ENTER);
    $this->assertFalse($this->isOpen(self::LETTERS));
    $assert->assertWaitOnAjaxRequest();
    $this->assertSame(['a'], $this->getCheckedValues(self::LETTERS));

    // Space toggles an option too.
    $this->pressKey(self::LETTERS . ' summary', self::KEY_ENTER);
    $this->pressKey(self::LETTERS . ' input[value="b"]', ' ');
    $assert->assertWaitOnAjaxRequest();
    $this->assertSame(['a', 'b'], $this->getCheckedValues(self::LETTERS));

    // Enter selects all options of a group.
    $this->pressKey(self::LOCATION . ' summary', self::KEY_ENTER);
    $this->pressKey($this->getGroupToggleSelector('Oregon'), self::KEY_ENTER);
    $assert->assertWaitOnAjaxRequest();
    $this->assertEqualsCanonicalizing(
      [$this->getLocationTid('Portland'), $this->getLocationTid('Eugene')],
      $this->getCheckedValues(self::LOCATION),
    );
  }

  /**
   * Tests moving between the options with arrow keys.
   */
  public function testArrowKeys(): void {
    $this->useDropdownFilter();
    $this->drupalGet('bef-test');
    $assert = $this->assertSession();
    $letter = fn (string $value) => self::LETTERS . ' input[value="' . $value . '"]';

    // ArrowDown on the button opens the dropdown and focuses the first option.
    $this->pressKey(self::LETTERS . ' summary', self::KEY_DOWN);
    $this->assertTrue($this->isOpen(self::LETTERS));
    $this->assertTrue($this->isFocused($letter('a')));

    // ArrowDown and ArrowUp move between the options.
    $this->sendKey(self::KEY_DOWN);
    $this->assertTrue($this->isFocused($letter('b')));
    $this->sendKey(self::KEY_DOWN);
    $this->assertTrue($this->isFocused($letter('c')));
    $this->sendKey(self::KEY_UP);
    $this->assertTrue($this->isFocused($letter('b')));

    // End and Home move to the last and first option. ArrowDown stays on the
    // last option.
    $this->sendKey(self::KEY_END);
    $this->assertTrue($this->isFocused($letter('e')));
    $this->sendKey(self::KEY_DOWN);
    $this->assertTrue($this->isFocused($letter('e')));
    $this->sendKey(self::KEY_HOME);
    $this->assertTrue($this->isFocused($letter('a')));

    // ArrowUp on the first option moves back to the button and keeps the
    // dropdown open.
    $this->sendKey(self::KEY_UP);
    $this->assertTrue($this->isFocused(self::LETTERS . ' summary'));
    $this->assertTrue($this->isOpen(self::LETTERS));

    // Moving doesn't change the selection, Space does.
    $this->sendKey(self::KEY_DOWN);
    $this->sendKey(self::KEY_DOWN);
    $this->assertSame([], $this->getCheckedValues(self::LETTERS));
    $this->sendKey(' ');
    $assert->assertWaitOnAjaxRequest();
    $this->assertSame(['b'], $this->getCheckedValues(self::LETTERS));

    // Group toggles are included, in display order: first the toggle of the
    // first group, then the first option of that group.
    $this->pressKey(self::LOCATION . ' summary', self::KEY_DOWN);
    $this->assertTrue($this->isFocused(self::LOCATION . ' [data-dropdown-filter-group]'));
    $this->sendKey(self::KEY_DOWN);
    $this->assertTrue($this->isFocused(self::LOCATION . ' input:not([data-dropdown-filter-group])'));
  }

  /**
   * Tests closing the dropdown when interacting with other elements.
   */
  public function testClosing(): void {
    $this->useDropdownFilter();
    $this->drupalGet('bef-test');

    // Opening a dropdown closes the others. The open dropdown covers the
    // other filters in the test theme, so click with JavaScript.
    $this->click(self::LETTERS . ' summary');
    $this->getSession()->executeScript('document.querySelector(\'' . self::LOCATION . ' summary\').click()');
    $this->assertFalse($this->isOpen(self::LETTERS));
    $this->assertTrue($this->isOpen(self::LOCATION));

    // Clicking outside closes the dropdown.
    $this->getSession()->executeScript('document.body.click()');
    $this->assertFalse($this->isOpen(self::LOCATION));

    // Clicking the filter label toggles the dropdown.
    $this->click(self::LETTERS . ' .form-item__label');
    $this->assertTrue($this->isOpen(self::LETTERS));
    $this->click(self::LETTERS . ' .form-item__label');
    $this->assertFalse($this->isOpen(self::LETTERS));
  }

  /**
   * Clicks an option of a filter by its label.
   *
   * @param string $filter
   *   The filter selector.
   * @param string $label
   *   The option label.
   */
  private function clickOption(string $filter, string $label): void {
    $this->assertSession()
      ->elementExists('css', $filter)
      ->find('xpath', './/label[contains(@class, "multi-select-menuitem")][normalize-space(.)="' . $label . '"]')
      ->click();
  }

  /**
   * Gets the CSS selector of a group toggle checkbox.
   *
   * @param string $group
   *   The group label.
   *
   * @return string
   *   The CSS selector.
   */
  private function getGroupToggleSelector(string $group): string {
    $index = $this->getSession()->evaluateScript('Array.from(document.querySelectorAll(\'' . self::LOCATION . ' .select-group\'))
      .findIndex((fieldset) => fieldset.querySelector("legend").textContent.trim() === "' . $group . '")');
    $this->assertGreaterThanOrEqual(0, $index, "Group $group exists.");
    return self::LOCATION . ' .select-group:nth-of-type(' . ($index + 1) . ') [data-dropdown-filter-group]';
  }

  /**
   * Gets the clickable label of a group.
   *
   * @param string $group
   *   The group label.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The label.
   */
  private function getGroupLabel(string $group) {
    return $this->assertSession()->elementExists('xpath', '//*[@data-dropdown-filter="field_bef_location_target_id"]//legend/label[normalize-space(.)="' . $group . '"]');
  }

  /**
   * Gets the group toggle checkbox.
   *
   * @param string $group
   *   The group label.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The checkbox.
   */
  private function getGroupToggle(string $group) {
    return $this->assertSession()->elementExists('xpath', '//*[@data-dropdown-filter="field_bef_location_target_id"]//legend[normalize-space(.)="' . $group . '"]//input');
  }

  /**
   * Gets the values of the checked options of a filter.
   *
   * @param string $filter
   *   The filter selector.
   *
   * @return array
   *   The values.
   */
  private function getCheckedValues(string $filter): array {
    return $this->getSession()->evaluateScript('Array.from(document.querySelectorAll(\'' . $filter . ' input[name]:checked\')).map((input) => input.value)');
  }

  /**
   * Checks whether the dropdown of a filter is open.
   *
   * @param string $filter
   *   The filter selector.
   *
   * @return bool
   *   TRUE if the dropdown is open.
   */
  private function isOpen(string $filter): bool {
    return $this->getSession()->evaluateScript('document.querySelector(\'' . $filter . ' details\').open');
  }

  /**
   * Checks whether an element has focus.
   *
   * @param string $selector
   *   The element selector.
   *
   * @return bool
   *   TRUE if the element has focus.
   */
  private function isFocused(string $selector): bool {
    return $this->getSession()->evaluateScript('document.activeElement === document.querySelector(\'' . $selector . '\')');
  }

  /**
   * Focuses an element and presses a key with the real keyboard.
   *
   * Mink's keyPress() dispatches synthetic events, which don't trigger the
   * browser's default actions such as opening a details element.
   *
   * @param string $selector
   *   The element selector.
   * @param string $key
   *   The key, as a character or WebDriver key code.
   */
  private function pressKey(string $selector, string $key): void {
    $this->assertSession()->elementExists('css', $selector);
    $this->getSession()->executeScript('document.querySelector(\'' . $selector . '\').focus()');
    $this->sendKey($key);
  }

  /**
   * Presses a key on the focused element.
   *
   * @param string $key
   *   The key, as a character or WebDriver key code.
   */
  private function sendKey(string $key): void {
    /** @var \Behat\Mink\Driver\Selenium2Driver $driver */
    $driver = $this->getSession()->getDriver();
    $session = $driver->getWebDriverSession();
    $session->postActions([
      'actions' => [
        [
          'type' => 'key',
          'id' => 'keyboard',
          'actions' => [
            ['type' => 'keyDown', 'value' => $key],
            ['type' => 'keyUp', 'value' => $key],
          ],
        ],
      ],
    ]);
    $session->deleteActions();
  }

}
