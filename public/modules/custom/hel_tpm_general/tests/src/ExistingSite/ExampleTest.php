<?php

namespace Drupal\Tests\hel_tpm_general\ExistingSite;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * A model test case using traits from Drupal Test Traits.
 */
class ExampleTest extends ExistingSiteBase {

  use LoginTrait;

  protected function setUp() {
    parent::setUp();
  }

  /**
   * An example test method; note that Drupal API's and Mink are available.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testLlama() {
    // Creates a user. Will be automatically cleaned up at the end of the test.
    $author = $this->createUser([], null, true);

    // Create a taxonomy term. Will be automatically cleaned up at the end of the test.
    $vocab = Vocabulary::load('service_tags');
    $term = $this->createTerm($vocab);

    // Create a "Llama" article. Will be automatically cleaned up at end of test.
    $node = $this->createNode([
      'title' => 'Llama',
      'type' => 'basic_page',
      'uid' => $author->id(),
    ]);
    $node->setPublished()->save();
    $this->assertEquals($author->id(), $node->getOwnerId());

    // We can browse pages.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(200);

    // We can login and browse admin pages.
    $this->drupalLogin($author);
    $this->drupalGet($node->toUrl('edit-form'));
  }
}
