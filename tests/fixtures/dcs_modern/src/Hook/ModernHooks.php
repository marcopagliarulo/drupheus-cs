<?php

declare(strict_types=1);

namespace Drupal\dcs_modern\Hook;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Provides class hooks that fail when autowired.
 */
final class ModernHooks {

  /**
   * Constructs a ModernHooks object.
   */
  public function __construct(protected readonly CacheBackendInterface $cache) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
  }

}
