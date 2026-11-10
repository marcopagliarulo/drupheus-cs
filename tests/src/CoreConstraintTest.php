<?php

declare(strict_types=1);

namespace DrupalCS\Tests;

use DrupalCS\CoreConstraint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests reading the lowest version of a core constraint.
 */
final class CoreConstraintTest extends TestCase {

  /**
   * Tests the lowest version of each constraint form.
   */
  #[DataProvider('constraints')]
  public function testLowestVersion(string $constraint, ?string $expected): void {
    self::assertSame($expected, CoreConstraint::lowestVersion($constraint));
  }

  /**
   * Provides constraints and their lowest version.
   *
   * @return array<string, array{string, string|null}>
   *   The test cases.
   */
  public static function constraints(): array {
    return [
      'caret' => ['^11', '11.0.0'],
      'minor' => ['^11.2', '11.2.0'],
      'patch' => ['>=11.1.3', '11.1.3'],
      'alternatives' => ['^12 || ^11.3', '11.3.0'],
      'single pipe' => ['^11.1 | ^12', '11.1.0'],
      'range' => ['>=11.2 <13', '11.2.0'],
      'wildcard' => ['*', NULL],
    ];
  }

}
