<?php

declare(strict_types=1);

namespace Drupal\dcs_standard;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a promotion fixture.
 */
final class Promotable {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a Promotable object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

}
