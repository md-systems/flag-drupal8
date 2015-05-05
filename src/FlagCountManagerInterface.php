<?php

/**
 * @file
 * Contains Drupal\flag\FlagCountManagerInterface.
 */

namespace Drupal\flag;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagInterface;

/**
 * Interface FlagCountManagerInterface.
 *
 * @package Drupal\flag
 */
interface FlagCountManagerInterface {

  /**
   * Get flag counts for all flags on a node.
   *
   * @param $entity
   *   The entity.
   *
   * @return
   *   The flag count with the flag names as array keys.
   */
  public function getCounts(EntityInterface $entity);

  /**
   * Get the count of flags for a certain entity.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag.
   *
   * @return
   *   The flag count with the flag name and entity type as the array key.
   */
  public function getEntityCounts(FlagInterface $flag);

  /**
   * Get the total count of items flagged within a flag.
   *
   * @param \Drupal\flag\FlagInterface
   *   The flag for which to retrieve a flag count.
   * @param $reset
   *   (optional) Reset the internal cache and execute the SQL query another time.
   */
  public function getTotals(FlagInterface $flag, $reset = FALSE);

  /**
   * Get the user's flag count.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user object.
   *
   * @return
   *   The flag count with the flag name and the uid as the array key.
   */
  public function getUserCounts(FlagInterface $flag, AccountInterface $user);

}
