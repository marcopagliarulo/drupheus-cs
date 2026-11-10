<?php

declare(strict_types=1);

namespace DrupalCS\PHPStan\Drupal;

use DrupalCS\CoreConstraint;

/**
 * Determines the lowest Drupal version analyzed code has to run on.
 */
final class CoreVersion {

  /**
   * Constructs a CoreVersion object.
   */
  public function __construct(
    private readonly ExtensionLocator $locator,
    private readonly ?string $override,
  ) {}

  /**
   * Returns the minimum core version for a file.
   *
   * Extensions without a usable constraint, such as test modules using "*",
   * take the constraint of the extension they are nested in.
   */
  public function minimumFor(string $file): string {
    if ($this->override !== NULL) {
      return CoreConstraint::lowestVersion($this->override) ?? $this->override;
    }
    foreach ($this->locator->extensionsFor($file) as $extension) {
      $constraint = $extension['info']['core_version_requirement'] ?? NULL;
      $version = is_string($constraint) ? CoreConstraint::lowestVersion($constraint) : NULL;
      if ($version !== NULL) {
        return $version;
      }
    }
    return \Drupal::VERSION;
  }

  /**
   * Checks whether a file's minimum core version includes a feature.
   */
  public function supports(string $file, string $feature): bool {
    return version_compare($this->minimumFor($file), $feature, '>=');
  }

}
