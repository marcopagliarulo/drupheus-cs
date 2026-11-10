<?php

declare(strict_types=1);

namespace Drupal\dcs_modern\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a controller needing an attribute to autowire.
 */
final class NeedsAttributeController extends ControllerBase {

  /**
   * Constructs a NeedsAttributeController object.
   */
  public function __construct(
    protected readonly CacheBackendInterface $cache,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('cache.default'));
  }

}
