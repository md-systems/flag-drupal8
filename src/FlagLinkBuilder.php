<?php

/**
 * @file
 * Contains Drupal\flag\FlagLinkBuilder.
 */

namespace Drupal\flag;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\flag\FlagServiceInterface;

/**
 * Provides a lazy builder for flag links.
 *
 * @package Drupal\flag
 */
class FlagLinkBuilder implements FlagLinkBuilderInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Constructor.
   *
   * @param Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param Drupal\flag\FlagServiceInterface $flag_service
   *   The flag service.
   */
  public function __construct(EntityManagerInterface $entity_manager, FlagServiceInterface $flag_service) {
    $this->entityManager = $entity_manager;
    $this->flagService = $flag_service;
  }

  /**
   * {@inheritdoc}
   */
  public function build($entity_type_id, $entity_id, $flag_id) {
    $entity = $this->entityManager->getStorage($entity_type_id)->load($entity_id);
    $flag = $this->flagService->getFlagById($flag_id);

    $action = 'flag';
    if ($flag->isFlagged($entity)) {
      $action = 'unflag';
    }

    // Only display if the user does have access.
    if ($flag->hasActionAccess($action)) {
      $link_type_plugin = $flag->getLinkTypePlugin();
      $link = $link_type_plugin->buildLink($action, $flag, $entity);
      // The actual render array must be in a nested key, due to a bug in
      // lazy builder handling that does not properly render top-level #type
      // elements.
      return ['link' => $link];
    }

    // Lazy builders must always return a render array.
    return [];
  }

}
