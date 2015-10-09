<?php
/**
 * @file
 * Contains \Drupal\flag\Tests\FlagCountsTest.
 */

namespace Drupal\flag\Tests;

use Drupal\flag\Tests\FlagTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests the Flag counts API.
 *
 * @todo These are currently very basic, and simply test that the various count
 * functions return a sane value and don't crash.
 *
 * @group flag
 */
class FlagCountsTest extends FlagTestBase {

  /**
   * The flag.
   *
   * @var \Drupal\flag\FlagInterface
   *
   */
  protected $flag;

  /**
   * The node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * User object.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->flagService = \Drupal::service('flag');

    // Create a flag.
    $this->flag = $this->createFlag('node', ['article'], 'reload');

    // Create a user who may flag.
    $this->adminUser = $this->drupalCreateUser([
      'administer flags',
    ]);

    // Create a node to flag.
    $this->node = Node::create([
      'body' => [
        [
          'value' => $this->randomMachineName(32),
          'format' => filter_default_format(),
        ]
      ],
      'type' => 'article',
      'title' => $this->randomMachineName(8),
    ]);
    $this->node->save();
  }

  /**
   * Tests that counts are kept in sync and can be retrieved.
   */
  public function testFlagCounts() {
    // Flag the node.
    $this->flagService->flag($this->flag, $this->node, $this->adminUser);

    $flagCountService = \Drupal::service('flag.count');

    // Check each of the count API functions.
    $flag_get_entity_flag_counts = $flagCountService->getEntityCounts($this->flag, 'node');
    $this->assertEqual($flag_get_entity_flag_counts, 1, "getEntityFlagCounts() returns the expected count.");

    $flag_get_user_flag_counts = $flagCountService->getUserCounts($this->flag, $this->adminUser);
    $this->assertEqual($flag_get_user_flag_counts, 1, "getUserFlagCounts() returns the expected count.");

    $flag_get_counts = $flagCountService->getCounts($this->node);
    $this->assertEqual($flag_get_counts[$this->flag->id()], 1, "getCounts() returns the expected count.");

    $flag_get_flag_counts = $flagCountService->getTotals($this->flag);
    $this->assertEqual($flag_get_flag_counts, 1, "getFlagTotalCounts() returns the expected count.");

    $this->flagService->reset($this->flag);
    drupal_static_reset();
    $flag_get_flag_counts = $flagCountService->getTotals($this->flag);
    $this->assertEqual($flag_get_flag_counts, 0, "getCounts() on reset flag returns the expected count.");
  }

}
