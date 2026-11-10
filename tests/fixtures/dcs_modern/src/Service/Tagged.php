<?php

declare(strict_types=1);

namespace Drupal\dcs_modern\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides a service fixture.
 */
final class Tagged {

  /**
   * Constructs a Tagged object.
   */
  public function __construct(protected readonly iterable $bins) {}

}
