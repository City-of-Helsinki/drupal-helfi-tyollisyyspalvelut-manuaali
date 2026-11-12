<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_general\Unit;

use Drupal\Core\Form\FormStateInterface;
use Drupal\hel_tpm_general\Plugin\Field\FieldWidget\TelephoneWithTitleWidget;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Tests that validation errors are shown on the right input.
 */
#[CoversClass(TelephoneWithTitleWidget::class)]
#[Group('hel_tpm_general')]
final class TelephoneWithTitleWidgetTest extends UnitTestCase {

  /**
   * Property paths and the input that should get the error.
   */
  public static function propertyPathProvider(): array {
    return [
      'title' => ['0.title', 'title'],
      'number' => ['0.value', 'value'],
      'other delta' => ['2.title', 'title'],
      'item level' => ['0', 'item'],
      'unknown property' => ['0.unknown', 'item'],
    ];
  }

  /**
   * Tests mapping a violation to the title or the number input.
   */
  #[DataProvider('propertyPathProvider')]
  public function testErrorElement(string $property_path, string $expected): void {
    $element = [
      '#id' => 'item',
      'title' => ['#id' => 'title'],
      'value' => ['#id' => 'value'],
    ];
    $violation = new ConstraintViolation('Error', NULL, [], NULL, $property_path, NULL);

    // errorElement() does not use the widget state, so no constructor needed.
    $widget = (new \ReflectionClass(TelephoneWithTitleWidget::class))->newInstanceWithoutConstructor();
    $error_element = $widget->errorElement($element, $violation, [], $this->createMock(FormStateInterface::class));

    $this->assertSame($expected, $error_element['#id']);
  }

}
