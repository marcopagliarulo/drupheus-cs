<?php

declare(strict_types=1);

namespace DrupheusCs;

/**
 * Reads the lowest Drupal version allowed by a core_version_requirement.
 */
final class CoreConstraint {

  /**
   * Returns the lowest version of a constraint, or NULL if it has none.
   *
   * @param string $constraint
   *   A constraint such as "^11.1 || ^12".
   *
   * @return string|null
   *   A three-part version such as "11.1.0".
   */
  public static function lowestVersion(string $constraint): ?string {
    $lowest = NULL;
    foreach (preg_split('/\s*\|\|?\s*/', $constraint) ?: [] as $alternative) {
      if (preg_match('/\d+(?:\.\d+){0,2}/', $alternative, $matches) !== 1) {
        continue;
      }
      $version = implode('.', array_pad(explode('.', $matches[0]), 3, '0'));
      if ($lowest === NULL || version_compare($version, $lowest, '<')) {
        $lowest = $version;
      }
    }
    return $lowest;
  }

}
