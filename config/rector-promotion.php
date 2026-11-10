<?php

/**
 * @file
 * Rector configuration converting assignments to promoted properties.
 *
 * Parameters take the name of their property, the only name a Drupal property
 * may have.
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return RectorConfig::configure()
  ->withConfiguredRule(ClassPropertyAssignToConstructorPromotionRector::class, [
    ClassPropertyAssignToConstructorPromotionRector::RENAME_PROPERTY => TRUE,
    ClassPropertyAssignToConstructorPromotionRector::ALLOW_MODEL_BASED_CLASSES => TRUE,
  ]);
