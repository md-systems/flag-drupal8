<?php

/**
 * @file
 * Contains Drupal\flag\FlagCountManager.
 */

namespace Drupal\flag;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\Event\FlagEvents;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\FlagCountManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\flag\FlagInterface;

/**
 * Class FlagCountManager.
 *
 * @package Drupal\flag
 */
class FlagCountManager implements FlagCountManagerInterface, EventSubscriberInterface {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a FlagCountManager.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function getCounts(EntityInterface $entity) {
    $counts = &drupal_static(__FUNCTION__);

    $entity_type = $entity->getEntityTypeId();
    $entity_id = $entity->id();
    if (!isset($counts[$entity_type][$entity_id])) {
      $counts[$entity_type][$entity_id] = [];
      $query = $this->connection->select('flag_counts', 'fc');
      $result = $query
        ->fields('fc', ['fid', 'count'])
        ->condition('fc.entity_type', $entity_type)
        ->condition('fc.entity_id', $entity_id)
        ->execute();
      foreach ($result as $row) {
        $counts[$entity_type][$entity_id][$row->fid] = $row->count;
      }
    }

    return $counts[$entity_type][$entity_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityCounts(FlagInterface $flag) {
    $counts = &drupal_static(__FUNCTION__);

    $flag_id = $flag->id();
    $entity_type = $flag->getFlaggableEntityTypeId();

    // We check to see if the flag count is already in the cache,
    // if it's not, run the query.
    if (!isset($counts[$flag_id][$entity_type])) {
      $counts[$flag_id][$entity_type] = [];
      $result = $this->connection->select('flagging', 'f')
        ->fields('f', ['fid'])
        ->condition('fid', $flag_id)
        ->condition('entity_type', $entity_type)
        ->countQuery()
        ->execute()
        ->fetchField();
      $counts[$flag_id][$entity_type] = $result;
    }

    return $counts[$flag_id][$entity_type];
  }

  /**
   * {@inheritdoc}
   */
  public function getTotals(FlagInterface $flag) {
    $counts = &drupal_static(__FUNCTION__);
    $flag_name = $flag->id();

    if (!isset($counts[$flag_name])) {
      $counts[$flag_name] = $this->connection->select('flag_counts', 'fc')
        ->fields('fc', array('fid'))
        ->condition('fid', $flag_name)
        ->countQuery()
        ->execute()
        ->fetchField();
    }

    return $counts[$flag_name];
  }

  /**
   * {@inheritdoc}
   */
  public function getUserCounts(FlagInterface $flag, AccountInterface $user) {
    $counts = &drupal_static(__FUNCTION__);

    $flag_id = $flag->id();
    $uid = $user->id();

    // We check to see if the flag count is already in the cache,
    // if it's not, run the query.
    if (!isset($counts[$flag_id][$uid])) {
      $counts[$flag_id][$uid] = [];
      $result = $this->connection->select('flagging', 'f')
        ->fields('f', ['fid'])
        ->condition('fid', $flag_id)
        ->condition('uid', $uid)
        ->countQuery()
        ->execute()
        ->fetchField();
      $counts[$flag_id][$uid] = $result;
    }

    return $counts[$flag_id][$uid];
  }

  /**
   * Increments count of flagged entities.
   *
   * @param \Drupal\flag\Event\FlaggingEvent $event
   *   The flagging event.
   */
  public function incrementFlagCounts(FlaggingEvent $event) {
    $this->connection->merge('flag_counts')
      ->key([
        'fid' => $event->getFlag()->id(),
        'entity_id' => $event->getEntity()->id(),
        'entity_type' => $event->getEntity()->getEntityTypeId(),
      ])
      ->fields([
        'last_updated' => REQUEST_TIME,
        'count' => 1,
      ])
      ->expression('count', 'count + :inc', [':inc' => 1])
      ->execute();
  }

  /**
   * Decrements count of flagged entities.
   *
   * @param \Drupal\flag\Event\FlaggingEvent $event
   *   The flagging Event.
   */
  public function decrementFlagCounts(FlaggingEvent $event) {

    /* @var \Drupal\flag\FlaggingInterface flag */
    $flag = $event->getFlag();
    /* @var Drupal\Core\Entity\EntityInterface $entity */
    $entity = $event->getEntity();

    $count_result = $this->connection->select('flag_counts')
      ->fields(NULL, ['fid', 'entity_id', 'entity_type', 'count'])
      ->condition('fid', $flag->id())
      ->condition('entity_id', $entity->id())
      ->condition('entity_type', $entity->getEntityTypeId())
      ->execute()
      ->fetchAll();
    if (count($count_result) == 1) {
      $this->connection->delete('flag_counts')
        ->condition('fid', $flag->id())
        ->condition('entity_id', $entity->id())
        ->condition('entity_type', $entity->getEntityTypeId())
        ->execute();
    }
    else {
      $this->connection->update('flag_counts')
        ->expression('count', 'count - 1')
        ->condition('fid', $flag->id())
        ->condition('entity_id', $entity->id())
        ->condition('entity_type', $entity->getEntityTypeId())
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = array();
    $events[FlagEvents::ENTITY_FLAGGED][] = array('incrementFlagCounts', -100);
    $events[FlagEvents::ENTITY_UNFLAGGED][] = array('decrementFlagCounts', -100);
    return $events;
  }

}
