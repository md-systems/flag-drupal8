<?php
/**
 * @file
 * Contains \Drupal\flag\Tests\FlagResetTest.
 */

namespace Drupal\flag\Tests;

use Drupal\flag\Tests\FlagTestBase;
use Drupal\node\Entity\Node;

/**
 * Test resetting a flag through the user interface.
 *
 * @group flag
 */
class FlagResetTest extends FlagTestBase {

  /**
   * The flag.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flag;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The entity query service.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQueryManager;

  /**
   * User object.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * The node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->flagService = $this->container->get('flag');

    $this->entityQueryManager = $this->container->get('entity.query');

    // Create a flag.
    $this->flag = $this->createFlag('node', ['article'], 'reload');

    // Create a user who may flag.
    $this->adminUser = $this->drupalCreateUser([
      'administer flags',
    ]);

    // Login before setting up flag.
    $this->drupalLogin($this->adminUser);

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
  public function testFlagResetUI() {
    // Flag the node.
    $this->flagService->flag($this->flag, $this->node, $this->adminUser);

    $ids_before = $this->entityQueryManager->get('flagging')
      ->condition('flag_id', $this->flag->id())
      ->condition('entity_type', 'node')
      ->condition('entity_id', $this->node->id())
      ->execute();

    $this->assertEqual(count($ids_before), 1, "The flag has one flagging.");

    // Go to the reset form for the flag.
    $this->drupalGet('flag/reset/' . $this->flag->id());

    $this->assertText($this->t('Are you sure you want to reset the Flag'));

    $this->drupalPostForm(NULL, [], $this->t('Reset'));

    $ids_after = $this->entityQueryManager->get('flagging')
      ->condition('flag_id', $this->flag->id())
      ->condition('entity_type', 'node')
      ->condition('entity_id', $this->node->id())
      ->execute();

    $this->assertEqual(count($ids_after), 0, "The flag has no flaggings after being reset.");
  }

}
