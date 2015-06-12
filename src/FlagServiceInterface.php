<?php
/**
 * @file
 * Contains \Drupal\flag\FlagServiceInterface.
 */

namespace Drupal\flag;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\flag\FlagInterface;

/**
 * Flag service interface.
 */
interface FlagServiceInterface {

  /**
   * List all flags available.
   *
   * If all the parameters are omitted, a list of all flags will be returned.
   *
   * @param string $entity_type
   *   (optional) The type of entity for which to load the flags.
   * @param string $bundle
   *   (optional) The bundle for which to load the flags.
   * @param AccountInterface $account
   *   (optional) The user account to filter available flags. If not set, all
   *   flags for the given entity and bundle will be returned.
   *
   * @return array
   *   An array of flag entities, keyed by the entity IDs.
   */
  public function getFlags($entity_type = NULL, $bundle = NULL, AccountInterface $account = NULL);

  /**
   * Get a flagging that already exists.
   *
   * @param FlagInterface $flag
   *   The flag.
   * @param EntityInterface $entity
   *   The flaggable entity.
   * @param AccountInterface $account
   *   (optional) The account of the flagging user. If omitted, the flagging for
   *   the current user will be returned.
   *
   * @return FlaggingInterface|null
   *   The flagging or NULL if the flagging is not found.
   *
   */
  public function getFlagging(FlagInterface $flag, EntityInterface $entity, AccountInterface $account = NULL);

  /**
   * Get all flaggings for the given entity, flag, and optionally, user.
   *
   * @param FlagInterface $flag
   *   (optional) The flag entity. If NULL, flaggings for any flag will be
   *   returned.
   * @param EntityInterface $entity
   *   (optional) The flaggable entity. If NULL, flaggings for any entity will be
   *   returned.
   * @param AccountInterface $account
   *   (optional) The account of the flagging user. If NULL, flaggings for any
   *   user will be returned.
   *
   * @return array
   *   An array of flaggings.
   */
   public function getFlaggings(FlagInterface $flag = NULL, EntityInterface $entity = NULL, AccountInterface $account = NULL);

  /**
   * Load the flag entity given the ID.
   *
   * @param int $flag_id
   *   The ID of the flag to load.
   *
   * @return FlagInterface|null
   *   The flag entity.
   */
  public function getFlagById($flag_id);

  /**
   * Loads the flaggable entity given the flag entity and entity ID.
   *
   * @param FlagInterface $flag
   *   The flag entity.
   * @param int $entity_id
   *   The ID of the flaggable entity.
   *
   * @return EntityInterface|null
   *   The flaggable entity object.
   */
  public function getFlaggableById(FlagInterface $flag, $entity_id);

  /**
   * Get a list of users that have flagged an entity.
   *
   * @param EntityInterface $entity
   *   The entity object.
   * @param FlagInterface $flag
   *   (optional) The flag entity to which to restrict results.
   *
   * @return array
   *   An array of users who have flagged the entity.
   */
  public function getFlaggingUsers(EntityInterface $entity, FlagInterface $flag = NULL);

  /**
   * Flags the given entity given the flag and entity objects.
   *
   * @param FlagInterface $flag
   *   The flag entity.
   * @param EntityInterface $entity
   *   The entity to flag.
   * @param AccountInterface $account
   *   (optional) The account of the user flagging the entity. If not given,
   *   the current user is used.
   *
   * @return FlaggingInterface|null
   *   The flagging.
   *
   * @throws \LogicException
   *   An exception is thrown if the given flag, entity, and account are not
   *   compatible in some way:
   *   - The flag applies to a different entity type from the given entity.
   *   - The flag does not apply to the entity's bundle.
   *   - The entity is already flagged with this flag by the user.
   */
  public function flag(FlagInterface $flag, EntityInterface $entity, AccountInterface $account = NULL);

  /**
   * Unflags the given entity for the given flag.
   *
   * @param FlagInterface $flag
   *   The flag being unflagged.
   * @param EntityInterface $entity
   *   The entity to unflag.
   * @param AccountInterface $account
   *   (optional) The account of the user that created the flagging.
   *
   * @return array
   *   An array of flagging IDs to delete.
   *
   * @throws \LogicException
   *   An exception is thrown if the given flag, entity, and account are not
   *   compatible in some way:
   *   - The flag applies to a different entity type from the given entity.
   *   - The flag does not apply to the entity's bundle.
   *   - The entity is not currently flagged with this flag by the user.
   */
  public function unflag(FlagInterface $flag, EntityInterface $entity, AccountInterface $account = NULL);

}
