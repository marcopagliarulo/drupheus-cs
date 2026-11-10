<?php

declare(strict_types=1);

namespace Drupal\dcs_modern\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a service fixture.
 */
final class Explicit {

  /**
   * Constructs a Explicit object.
   */
  public function __construct(protected readonly EntityTypeManagerInterface $typeManager, protected readonly CacheBackendInterface $cache) {}

}
