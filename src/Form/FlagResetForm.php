<?php
/**
 * @file
 * Contains \Drupal\flag\Form\FlagResetForm.
 */

namespace Drupal\flag\Form;

use Drupal\Core\Url;
use Drupal\flag\FlagInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the flag reset form.
 */
class FlagResetForm extends ConfirmFormBase {

  /**
   * The flag to reset.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flag;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state,
                            FlagInterface $flag = NULL) {
    $this->flag = $flag;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flag_reset_confirm_form';
  }


  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to reset the Flag %label?', [
      '%label' => $this->flag->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('All flaggings created with Flag %label will be deleted.', [
      '%label' => $this->flag->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Reset');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('flag.list');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::service('flag')->reset($this->flag);
    drupal_set_message($this->t('Flag %label was reset.', [
      '%label' => $this->flag->label(),
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
