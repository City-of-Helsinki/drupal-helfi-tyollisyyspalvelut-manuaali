<?php

declare(strict_types=1);

namespace Drupal\Tests\hel_tpm_url_shortener\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\hel_tpm_url_shortener\Entity\Shortenerredirect;
use Drupal\KernelTests\KernelTestBase;

/**
 * Test description.
 *
 * @group hel_tpm_url_shortener
 */
final class GarbageCollectorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system',
    'field',
    'hel_tpm_url_shortener',
    'redirect',
    'path_alias',
    'link',
  ];

  /**
   * Garbage collector service.
   */
  private mixed $garbageCollector;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('shortenerredirect');
    $this->garbageCollector = \Drupal::service('hel_tpm_url_shortener.garbage_collector');

    $datetime = new DrupalDateTime('-2 years');
    Shortenerredirect::create([
      'redirect_source' => '<front>',
      'shortened_link' => '<front>',
      'created' => $datetime->getTimestamp(),
      'changed' => $datetime->getTimestamp(),
    ])->save();

    Shortenerredirect::create([
      'redirect_source' => '<front>',
      'shortened_link' => '<front>',
      'create' => $datetime->getTimestamp(),
      'changed' => $datetime->getTimestamp(),
      'last_usage' => $datetime->add(\DateInterval::createFromDateString('1 year 6 months'))->getTimestamp(),
    ])->save();

    Shortenerredirect::create([
      'redirect_source' => '<front>',
      'shortened_link' => '<front>',
      'create' => $datetime->getTimestamp(),
      'changed' => $datetime->getTimestamp(),
    ])->save();
  }

  /**
   * Tests the functionality of the garbage collector.
   *
   * @return void
   *   Return nothing.
   */
  public function testGarbageCollector(): void {
    $this->garbageCollector->collect();
    $queue = $this->garbageCollector->getQueue();
    $this->assertEquals(1, $queue->numberOfItems());
    $item = $queue->claimItem();
    $this->assertNotEmpty($item->data);
    $this->assertArrayHasKey('1', $item->data);
    $this->assertArrayHasKey('id', $item->data[1]);
    $this->assertEquals('1', $item->data[1]['id']);
  }

}
