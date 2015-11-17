<?php
/**
 * @file
 * Contains \Drupal\flag\Form\FlagDeleteForm.
 */

namespace Drupal\flag\Form;

use Drupal\Core\Url;
use Drupal\Core\Entity\EntityDeleteForm;

/**
 * Provides the flag delete form.
 *
 * Unlike the FlagAddForm and FlagEditForm, this class does not derive from
 * FlagFormBase. Instead, it derives directly from EntityDeleteForm.
 * The reason is that we only need to provide a simple yes or no page when
 * deleting a flag.
 */
class FlagDeleteForm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('flag.list');
  }

}
