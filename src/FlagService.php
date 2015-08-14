<?php
/**
 * @file
 * Contains \Drupal\flag\FlagService.
 */

namespace Drupal\flag;


use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\Event\FlagEvents;
use Drupal\flag\Event\FlagResetEvent;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\flag\FlagTypePluginManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Flag service.
 */
class FlagService implements FlagServiceInterface {

  /**
   * The flag type plugin manager injected into the service.
   *
   * @var FlagTypePluginManager
   */
  private $flagTypeManager;

  /**
   * The event dispatcher injected into the service.
   *
   * @var EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * The entity query manager injected into the service.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  private $entityQueryManager;

  /**
   * The current user injected into the service.
   *
   * @var AccountInterface
   */
  private $currentUser;

  /*
   * @var EntityManagerInterface
   * */
  private $entityManager;

  /**
   * Constructor.
   *
   * @param FlagTypePluginManager $flag_type
   *   The flag type plugin manager.
   * @param EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   * @param QueryFactory $entity_query
   *   The entity query factory.
   * @param AccountInterface $current_user
   *   The current user.
   * @param EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(FlagTypePluginManager $flag_type,
                              EventDispatcherInterface $event_dispatcher,
                              QueryFactory $entity_query,
                              AccountInterface $current_user,
                              EntityManagerInterface $entity_manager) {
    $this->flagTypeManager = $flag_type;
    $this->eventDispatcher = $event_dispatcher;
    $this->entityQueryManager = $entity_query;
    $this->currentUser = $current_user;
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlags($entity_type = NULL, $bundle = NULL, AccountInterface $account = NULL) {
    $query = $this->entityQueryManager->get('flag');

    if ($entity_type != NULL) {
      $query->condition('entity_type', $entity_type);
    }

    if ($bundle != NULL) {
      $query->condition("types.*", $bundle);
    }

    $ids = $query->execute();
    $flags = $this->getFlagsByIds($ids);

    if ($account == NULL) {
      return $flags;
    }

    $filtered_flags = [];
    foreach ($flags as $flag) {
      if ($flag->hasActionAccess('flag', $account) || $flag->hasActionAccess('unflag', $account)) {
        $filtered_flags[] = $flag;
      }
    }

    return $filtered_flags;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlagging(FlagInterface $flag, EntityInterface $entity, AccountInterface $account = NULL) {
    if (empty($account)) {
      $account = $this->currentUser;
    }

    $flaggings = $this->getFlaggings($flag, $entity, $account);

    return !empty($flaggings) ? reset($flaggings) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlaggings(FlagInterface $flag = NULL, EntityInterface $entity = NULL, AccountInterface $account = NULL) {
    $query = $this->entityQueryManager->get('flagging');

    // The user is supplied with a flag that is not global.
    if (!empty($account) && !empty($flag) && !$flag->isGlobal()) {
      $query = $query->condition('uid', $account->id());
    }

    // The user is supplied but the flag is not.
    if (!empty($account) && empty($flag)) {
       $query = $query->condition('uid', $account->id());
    }
    if (!empty($flag)) {
      $query = $query->condition('flag_id', $flag->id());
    }

    if (!empty($entity)) {
      $query = $query->condition('entity_type', $entity->getEntityTypeId())
                     ->condition('entity_id', $entity->id());
    }

    $ids = $query->execute();

    return $this->getFlaggingsByIds($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getFlagById($flag_id) {
    return $this->entityManager->getStorage('flag')->load($flag_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getFlaggableById(FlagInterface $flag, $entity_id) {
    return $this->entityManager->getStorage($flag->getFlaggableEntityTypeId())->load($entity_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getFlaggingUsers(EntityInterface $entity, FlagInterface $flag = NULL) {
    $query = $this->entityQueryManager->get('users')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id());

    if (!empty($flag)) {
      $query = $query->condition('flag_id', $flag->id());
    }

    $ids = $query->execute();

    return $this->getFlaggingsByIds($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function flag(FlagInterface $flag, EntityInterface $entity, AccountInterface $account = NULL) {
    if (empty($account)) {
      $account = $this->currentUser;
    }

    // Check the entity type corresponds to the flag type.
    if ($flag->getFlaggableEntityTypeId() != $entity->getEntityTypeId()) {
      throw new \LogicException('The flag does not apply to entities of this type.');
    }

    // Check the bundle is allowed by the flag.
    if (!in_array($entity->bundle(), $flag->getTypes())) {
      throw new \LogicException('The flag does not apply to the bundle of the entity.');
    }

    // Check whether there is an existing flagging for the combination of flag,
    // entity, and user.
    if ($this->getFlagging($flag, $entity, $account)) {
      throw new \LogicException('The user has already flagged the entity with the flag.');
    }

    $flagging = $this->entityManager->getStorage('flagging')->create([
      'uid' => $account->id(),
      'flag_id' => $flag->id(),
      'entity_id' => $entity->id(),
      'entity_type' => $entity->getEntityTypeId(),
    ]);

    $flagging->save();

    $this->entityManager
      ->getViewBuilder($entity->getEntityTypeId())
      ->resetCache([
        $entity,
      ]);

    $this->eventDispatcher->dispatch(FlagEvents::ENTITY_FLAGGED, new FlaggingEvent($flag, $entity));

    return $flagging;
  }

  /**
   * {@inheritdoc}
   */
  public function unflag(FlagInterface $flag, EntityInterface $entity, AccountInterface $account = NULL) {
    // Check the entity type corresponds to the flag type.
    if ($flag->getFlaggableEntityTypeId() != $entity->getEntityTypeId()) {
      throw new \LogicException('The flag does not apply to entities of this type.');
    }

    // Check the bundle is allowed by the flag.
    if (!in_array($entity->bundle(), $flag->getTypes())) {
      throw new \LogicException('The flag does not apply to the bundle of the entity.');
    }

    // Check whether there is an existing flagging for the combination of flag,
    // entity, and user.
    if (!$this->getFlagging($flag, $entity, $account)) {
      throw new \LogicException('The entity is not flagged by the user.');
    }

    $this->eventDispatcher->dispatch(FlagEvents::ENTITY_UNFLAGGED, new FlaggingEvent($flag, $entity));

    $out = [];
    $flaggings = $this->getFlaggings($flag, $entity, $account);
    foreach ($flaggings as $flagging) {
      $out[] = $flagging->id();
      $flagging->delete();
    }

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function reset(FlagInterface $flag, EntityInterface $entity = NULL) {
    $query = $this->entityQueryManager->get('flagging')
      ->condition('flag_id', $flag->id());

    if (!empty($entity)) {
      $query->condition('entity_id', $entity->id());
    }

    // Count the number of flaggings to delete.
    $count = $query->count()
      ->execute();

    $this->eventDispatcher->dispatch(FlagEvents::FLAG_RESET, new FlagResetEvent($flag, $count));

    $flaggings = $this->getFlaggings($flag, $entity);
    foreach ($flaggings as $flagging) {
      $flagging->delete();
    }

    return $count;
  }

  /**
   * Loads flag entities given their IDs.
   *
   * @param int[] $ids
   *   The flag IDs.
   *
   * @return \Drupal\flag\FlagInterface[]
   *   An array of flags.
   */
  protected function getFlagsByIds(array $ids) {
    return $this->entityManager->getStorage('flag')->loadMultiple($ids);
  }

  /**
   * Loads flagging entities given their IDs.
   *
   * @param int[] $ids
   *   The flagging IDs.
   *
   * @return \Drupal\flag\FlaggingInterface[]
   *   An array of flaggings.
   */
  protected function getFlaggingsByIds(array $ids) {
    return $this->entityManager->getStorage('flagging')->loadMultiple($ids);
  }

}
