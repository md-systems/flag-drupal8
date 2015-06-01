<?php

/**
 * @file
 * Contains \Drupal\flag\Tests\FlagServiceTest.
 */

namespace Drupal\flag\Tests;

use Drupal\flag\Entity\Flag;
use Drupal\simpletest\WebTestBase;

/**
 * Tests the FlagService.
 *
 * @group flag
 */
class FlagServiceTest extends WebTestBase {

  /**
   * Set to TRUE to strict check all configuration saved.
   *
   * @see \Drupal\Core\Config\Testing\ConfigSchemaChecker
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  public static $modules = array(
    'flag',
    'node',
    'user',
  );

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagService;
   */
  protected $flagService;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->flagService = \Drupal::service('flag');
  }

  /**
   * Test the FlagService.
   */
  public function testFlagService() {
    $this->doTestFlagServiceGetFlag();
    $this->doTestFlagServiceFlagExceptions();
  }

  /**
   * Tests that flags once created can be retrieved.
   */
  public function doTestFlagServiceGetFlag() {
    $flag = Flag::create([
      'id' => 'test',
      'entity_type' => 'node',
      'types' => [
        'article',
      ],
      'flag_type' => 'flagtype_node',
      'link_type' => 'reload',
      'flagTypeConfig' => [],
      'linkTypeConfig' => [],
    ]);

    $flag->save();

    $result = $this->flagService->getFlags('node', 'article');
    $this->assertIdentical(count($result), 1, 'Found flag type');
  }

  /**
   * Test exceptions are thrown when flagging and unflagging.
   */
  public function doTestFlagServiceFlagExceptions() {
    $this->drupalCreateContentType(['type' => 'article']);
    $this->drupalCreateContentType(['type' => 'not_article']);

    $flag = Flag::create([
      'id' => 'test',
      'entity_type' => 'node',
      'types' => [
        'article',
      ],
      'flag_type' => 'flagtype_node',
      'link_type' => 'reload',
      'flagTypeConfig' => [],
      'linkTypeConfig' => [],
    ]);

    // The service methods don't check access, so our user can be anybody.
    $account = $this->drupalCreateUser();

    // Test flagging.

    // Try flagging an entity that's not a node: a user account.
    try {
      $this->flagService->flag($flag, $account, $account);
      $this->fail("The exception was not thrown.");
    }
    catch (\LogicException $e) {
      $this->pass("The flag() method throws an exception when the flag does not apply to the entity type of the flaggable entity.");
    }

    // Try flagging a node of the wrong bundle.
    $wrong_node = $this->drupalCreateNode(['type' => 'not_article']);
    try {
      $this->flagService->flag($flag, $wrong_node, $account);
      $this->fail("The exception was not thrown.");
    }
    catch (\LogicException $e) {
      $this->pass("The flag() method throws an exception when the flag does not apply to the bundle of the flaggable entity.");
    }

    // Flag the node, then try to flag it again.
    $flaggable_node = $this->drupalCreateNode(['type' => 'article']);
    $this->flagService->flag($flag, $flaggable_node, $account);

    try {
      $this->flagService->flag($flag, $flaggable_node, $account);
      $this->fail("The exception was not thrown.");
    }
    catch (\LogicException $e) {
      $this->pass("The flag() method throws an exception when the flaggable entity is already flagged by the user with the flag.");
    }

    // Test unflagging.

    // Try unflagging an entity that's not a node: a user account.
    try {
      $this->flagService->unflag($flag, $account, $account);
      $this->fail("The exception was not thrown.");
    }
    catch (\LogicException $e) {
      $this->pass("The unflag() method throws an exception when the flag does not apply to the entity type of the flaggable entity.");
    }

    // Try unflagging a node of the wrong bundle.
    try {
      $this->flagService->unflag($flag, $wrong_node, $account);
      $this->fail("The exception was not thrown.");
    }
    catch (\LogicException $e) {
      $this->pass("The unflag() method throws an exception when the flag does not apply to the bundle of the flaggable entity.");
    }

    // Create a new node that's not flagged, and try to unflag it.
    $unflagged_node = $this->drupalCreateNode(['type' => 'article']);
    try {
      $this->flagService->unflag($flag, $unflagged_node, $account);
      $this->fail("The exception was not thrown.");
    }
    catch (\LogicException $e) {
      $this->pass("The unflag() method throws an exception when the flaggable entity is not flagged by the user with the flag.");
    }
  }

}
