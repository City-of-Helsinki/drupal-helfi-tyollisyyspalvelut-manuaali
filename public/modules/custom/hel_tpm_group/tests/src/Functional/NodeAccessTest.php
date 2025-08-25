<?php

namespace Drupal\Tests\hel_tpm_group\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests basic node access with hel_tpm_group module.
 *
 * @group diff
 */
class NodeAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'group',
    'group_test_config',
    'hel_tpm_group',
    'message_notify',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createContentType(['type' => 'test_type']);
    $this->createUser();
    node_access_rebuild();
  }

  /**
   * Tests access to published node.
   */
  public function testPublished(): void {
    // Create an published node.
    $publishedNode = $this->createNode([
      'type' => 'test_type',
      'status' => TRUE,
    ]);
    $publishedNode->setTitle($this->randomString());
    $publishedNode->save();

    $user = $this->createUser(['access content']);
    $this->drupalLogin($user);

    $this->drupalGet($publishedNode->toUrl());
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests access to unpublished node.
   */
  public function testUnpublished(): void {
    $unpublishedNode = $this->createNode([
      'type' => 'test_type',
      'status' => FALSE,
    ]);
    $unpublishedNode->setTitle($this->randomString());
    $unpublishedNode->save();

    $user = $this->createUser(['access content']);
    $this->drupalLogin($user);

    $this->drupalGet($unpublishedNode->toUrl());
    $this->assertSession()->statusCodeEquals(403);
  }

}
