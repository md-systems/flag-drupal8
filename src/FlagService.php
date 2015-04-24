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
   * @var QueryFactory
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
  public function fetchDefinition($entity_type = NULL) {
    // @todo Add caching, PLS!
    if (!empty($entity_type)) {
      return $this->flagTypeManager->getDefinition($entity_type);
    }

    return $this->flagTypeManager->getDefinitions();
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

    $result = $query->execute();

    $flags = $this->entityManager->getStorage('flag')->loadMultiple($result);

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

    if (!empty($account) && !$flag->isGlobal()) {
      $query = $query->condition('uid', $account->id());
    }

    if (!empty($flag)) {
      $query = $query->condition('fid', $flag->id());
    }

    if (!empty($entity)) {
      $query = $query->condition('entity_type', $entity->getEntityTypeId())
                     ->condition('entity_id', $entity->id());
    }

    $result = $query->execute();

    $flaggings = [];
    foreach ($result as $flagging_id) {
      $flaggings[$flagging_id] = $this->entityManager->getStorage('flagging')->load($flagging_id);
    }

    return $flaggings;
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
      $query = $query->condition('fid', $flag->id());
    }

    $result = $query->execute();

    $flaggings = [];
    foreach ($result as $flagging_id) {
      $flaggings[$flagging_id] = $this->entityManager->getStorage('flagging')->load($flagging_id);
    }

    return $flaggings;
  }

  /**
   * {@inheritdoc}
   */
  public function flag(FlagInterface $flag, EntityInterface $entity, AccountInterface $account = NULL) {
    if (empty($account)) {
      $account = $this->currentUser;
    }

    $flagging = $this->entityManager->getStorage('flagging')->create([
      'type' => 'flag',
      'uid' => $account->id(),
      'fid' => $flag->id(),
      'entity_id' => $entity->id(),
      'entity_type' => $entity->getEntityTypeId(),
    ]);

    $flagging->save();

    $this->incrementFlagCounts($flag, $entity);

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
    $this->eventDispatcher->dispatch(FlagEvents::ENTITY_UNFLAGGED, new FlaggingEvent($flag, $entity));

    $out = [];
    $flaggings = $this->getFlaggings($flag, $entity, $account);
    foreach ($flaggings as $flagging) {
      $out[] = $flagging->id();

      $this->unflagByFlagging($flagging);

      $this->decrementFlagCounts($flag, $entity);
    }

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function unflagByFlagging(FlaggingInterface $flagging) {
    $flagging->delete();
  }

  /**
   * Increments count of flagged entities.
   *
   * @param FlagInterface $flag
   *   The flag to increment.
   * @param EntityInterface $entity
   *   The flaggable entity.
   */
  protected function incrementFlagCounts(FlagInterface $flag, EntityInterface $entity) {
    db_merge('flag_counts')
      ->key([
        'fid' => $flag->id(),
        'entity_id' => $entity->id(),
        'entity_type' => $entity->getEntityTypeId(),
      ])
      ->fields([
        'last_updated' => REQUEST_TIME,
        'count' => 1,
      ])
      ->expression('count', 'count + :inc', [':inc' => 1])
      ->execute();
  }

  /**
   * Reverts incrementation of count of flagged entities.
   *
   * @param FlagInterface $flag
   *   The flag to decrement.
   * @param EntityInterface $entity
   *   The flaggable entity.
   */
  protected function decrementFlagCounts(FlagInterface $flag, EntityInterface $entity) {
    $count_result = db_select('flag_counts')
      ->fields(NULL, ['fid', 'entity_id', 'entity_type', 'count'])
      ->condition('fid', $flag->id())
      ->condition('entity_id', $entity->id())
      ->condition('entity_type', $entity->getEntityTypeId())
      ->execute()
      ->fetchAll();
    if (count($count_result) == 1) {
      db_delete('flag_counts')
        ->condition('fid', $flag->id())
        ->condition('entity_id', $entity->id())
        ->condition('entity_type', $entity->getEntityTypeId())
        ->execute();
    }
    else {
      db_update('flag_counts')
        ->expression('count', 'count - 1')
        ->condition('fid', $flag->id())
        ->condition('entity_id', $entity->id())
        ->condition('entity_id', $entity->id())
        ->execute();
    }
  }

}
