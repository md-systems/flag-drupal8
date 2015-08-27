<?php
/**
 * @file
 * Contains \Drupal\flag\Plugin\Derivative\EntityFlagTypeDeriver.
 */

namespace Drupal\flag\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;

/**
 * Derivative class for entity flag subtypes plugin.
 */
class EntityFlagTypeDeriver extends DeriverBase {

  /**
   * Ignored types to prevent duplicate occurrences.
   *
   * @var array
   */
  protected $ignoredEntities = [
    'flagging',
  ];

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_def) {
    $derivatives = array();
    foreach (\Drupal::entityManager()->getDefinitions() as $entity_id => $entity_type) {
      if (in_array($entity_id, $this->ignoredEntities)) {
        continue;
      }
      // Skip config entity types.
      if (!$entity_type instanceof ContentEntityTypeInterface) {
        continue;
      }
      $derivatives[$entity_id] = [
        'title' => $entity_type->getLabel(),
        'entity_type' => $entity_id,
      ] + $base_plugin_def;
    }

    return $derivatives;
  }
}
