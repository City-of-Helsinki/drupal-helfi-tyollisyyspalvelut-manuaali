<?php

namespace Drupal\Tests\hel_tpm_general\ExistingSite;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * A model test case using traits from Drupal Test Traits.
 */
class SubGroupPermissionsTest extends ExistingSiteBase {

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

    $group_admin = $this->createUser([]);

    // We can login and browse admin pages.
    $this->drupalLogin($author);
  }
}
