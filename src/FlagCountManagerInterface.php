<?php

/**
 * @file
 * Contains Drupal\flag\FlagCountManagerInterface.
 */

namespace Drupal\flag;

/**
 * Interface FlagCountManagerInterface.
 *
 * @package Drupal\flag
 */
interface FlagCountManagerInterface {

  /**
   * Get flag counts for all flags on a node.
   *
   * @param $entity_type
   *   The entity type (usually 'node').
   * @param $entity_id
   *   The entity ID (usually the node ID).
   *
   * @return
   *   The flag count with the flag names as array keys.
   */
  public function getCounts($entity_type, $entity_id);

  /**
   * Get the count of flags for a certain entity.
   *
   * @param $flag
   *   The flag.
   * @param $entity_type
   *   The entity type (usually 'node').
   *
   * @return
   *   The flag count with the flag name and entity type as the array key.
   */
  public function getEntityCounts($flag, $entity_type);

  /**
   * Get the total count of items flagged within a flag.
   *
   * @param $flag_name
   *   The flag name for which to retrieve a flag count.
   * @param $reset
   *   (optional) Reset the internal cache and execute the SQL query another time.
   */
  public function getTotals($flag_name, $reset = FALSE);

  /**
   * Get the user's flag count.
   *
   * @param $flag
   *   The flag.
   * @param $user
   *   The user object.
   *
   * @return
   *   The flag count with the flag name and the uid as the array key.
   */
  public function getUserCounts($flag, $user);

}
