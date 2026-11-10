<?php

declare(strict_types=1);

namespace Drupal\dcs_modern\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a controller that fails when autowired.
 */
final class BrokenAutowireController extends ControllerBase {

  /**
   * Constructs a BrokenAutowireController object.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $typeManager,
    protected readonly CacheBackendInterface $cache,
    #[Autowire(service: 'dcs_modern.does_not_exist')]
    protected readonly CacheBackendInterface $otherCache,
    protected readonly string $label,
  ) {}

}
