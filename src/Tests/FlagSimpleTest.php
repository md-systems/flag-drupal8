<?php

/**
 * @file
 * Contains \Drupal\flag\FlagSimpleTest.
 */

namespace Drupal\flag\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\user\Entity\Role;


/**
 * Tests the Flag forms (add/edit/delete).
 */
class FlagSimpleTest extends WebTestBase {

  /**
   * @var string
   */
  protected $label = 'Test label 123';

  /**
   * @var string
   */
  protected $id = 'test_label_123';

  /**
   * @var string
   */
  protected $flagLinkType;

  /**
   * @var string
   */
  protected $nodeType = 'article';

  /**
   * @var string
   */
  protected $flag_confirmation = 'Are you sure you want to flag this content?';

  /**
   * @var string
   */
  protected $unflag_confirmation = 'Are you sure you want to unflag this content?';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('flag', 'node');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Flag form/s',
      'description' => 'Creates a flag, adds flag to node.',
      'group' => 'Flag',
    );
  }

  /**
   * Configures test base and executes test cases.
   */
  public function testFlagForm() {
    // Create and log in our user.
    $admin_user = $this->drupalCreateUser(array(
      'administer flags',
    ));
    $this->drupalLogin($admin_user);
    $this->doTestFlagAdd();
  }

  /**
   * Flag creation.
   */
  public function doTestFlagAdd() {
    // Create content type.
    $this->drupalCreateContentType(array('type' => $this->nodeType));

    // Test with minimal value requirement.
    $edit = array(
      'label' => $this->label,
      'id' => $this->id,
    );
    $this->drupalPostForm('/admin/structure/flags/add', $edit, t('Continue'));
    // Check for fieldset titles.
    $this->assertText(t('Messages'));
    $this->assertText(t('Flag access'));
    $this->assertText(t('Display options'));

    $edit = array(
      'types[' . $this->nodeType . ']' => $this->nodeType,
    );
    $this->drupalPostForm(NULL, $edit, t('Create Flag'));

    $this->assertText(t('Flag @this_label has been added.', array('@this_label' => $this->label)));

    // Continue test process.
    $this->doTestCreateNodeAndFlagIt();
  }

  /**
   * Node creation and flagging.
   */
  public function doTestCreateNodeAndFlagIt() {
    $node = $this->drupalCreateNode(array('type' => $this->nodeType));
    $node_id = $node->id();

    // Grant the flag permissions to the authenticated role, so that both
    // users have the same roles and share the render cache.
    $role = Role::load(DRUPAL_AUTHENTICATED_RID);
    $role->grantPermission('flag ' . $this->id);
    $role->grantPermission('unflag ' . $this->id);
    $role->save();

    // Create and login a new user.
    $user_1 = $this->drupalCreateUser();
    $this->drupalLogin($user_1);

    $this->drupalGet('/node/' . $node_id);
    $this->clickLink('Flag this item');
    $this->assertResponse(200);
    $this->assertLink('Unflag this item');

    // Switch user to check flagging link.
    $user_2 = $this->drupalCreateUser();
    $this->drupalLogin($user_2);
    $this->drupalGet('/node/' . $node_id);
    $this->assertResponse(200);
    $this->assertLink('Flag this item');

    // Switch back to first user and unflag.
    $this->drupalLogin($user_1);
    $this->drupalGet('/node/' . $node_id);

    $this->clickLink('Unflag this item');
    $this->assertResponse(200);
    $this->assertLink('Flag this item');
  }
}
