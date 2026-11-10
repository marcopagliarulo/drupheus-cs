<?php

declare(strict_types=1);

namespace DrupalCS\PHPStan\Drupal;

/**
 * Drupal versions that introduced the features the rules recommend.
 *
 * Each version was checked against the tagged drupal/core sources.
 */
final class Feature {

  public const string CLASS_HOOKS = '11.1.0';

  public const string PREPROCESS_CLASS_HOOKS = '11.2.0';

  public const string REQUIREMENTS_HOOKS = '11.2.0';

  public const string THEME_CLASS_HOOKS = '11.3.0';

  public const string PLUGIN_AUTOWIRE = '11.3.0';

  public const string FORM_AUTOWIRE = '11.4.0';

}
