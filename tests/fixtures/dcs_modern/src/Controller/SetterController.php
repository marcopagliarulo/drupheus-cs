<?php

declare(strict_types=1);

namespace Drupal\dcs_modern\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a controller with setter injection.
 */
final class SetterController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

}
