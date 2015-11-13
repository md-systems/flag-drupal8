<?php
/**
 * @file
 * Contains \Drupal\flag_bookmark\Tests\FlagBookmarkUITest.
 */

namespace Drupal\flag_bookmark\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * UI Test for flag_bookmark.
 *
 * @group flag_bookmark
 */
class FlagBookmarkUITest extends WebTestBase {

  public static $modules = array('flag', 'flag_bookmark', 'node');

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Create a test user and log in.
    $this->adminUser = $this->drupalCreateUser(array(
      'flag bookmark',
      'unflag bookmark',
      'create article content',
      'access content overview',
      'administer views',
    ));
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test the flag_bookmark UI.
   */
  public function testUI() {

    // Add the relationship.
    $this->drupalPostForm('admin/structure/views/nojs/add-handler/frontpage/page_1/relationship', [
      'name[node_field_data.flag_content_rel]' => TRUE,
    ], t('Add and configure relationships'));
    $this->drupalPostForm(NULL, array(), t('Apply'));
    $this->drupalPostForm(NULL, array(), t('Save'));

    // Add articles.
    $this->drupalPostForm('node/add/article', [
      'title[0][value]' => 'Article 1',
    ], t('Save'));

    // Check the front page does not have bookmark link.
    $this->drupalGet('node');
    $this->assertNoLink(t('Bookmark this'));

    // Check the link to bookmark exist.
    $this->drupalGet('node/1');
    $this->assertLink(t('Bookmark this'));

    // Bookmark article.
    $this->clickLink(t('Bookmark this'));

    // Check if the bookmark appears in the frontpage.
    $this->drupalGet('node');
    $this->assertLink(t('Remove bookmark'));
  }

}
