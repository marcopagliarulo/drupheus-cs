<?php

declare(strict_types=1);

namespace DrupalCS\PHPStan\Rule;

use DrupalCS\PHPStan\Drupal\ExtensionLocator;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Requires classes in an extension's namespace to live where PSR-4 maps it.
 *
 * Drupal\example\Foo\Bar belongs in example/src/Foo/Bar.php and
 * Drupal\Tests\example\Unit\BarTest in example/tests/src/Unit/BarTest.php.
 *
 * @implements \PHPStan\Rules\Rule<\PHPStan\Node\InClassNode>
 */
final class Psr4LocationRule implements Rule {

  /**
   * Constructs a Psr4LocationRule object.
   */
  public function __construct(
    private readonly ExtensionLocator $extensions,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getNodeType(): string {
    return InClassNode::class;
  }

  /**
   * {@inheritdoc}
   */
  public function processNode(Node $node, Scope $scope): array {
    $class = $node->getClassReflection();
    if ($class->isAnonymous()) {
      return [];
    }

    $file = $scope->getFile();
    $name = $class->getName();
    foreach ($this->extensions->extensionsFor($file) as $extension) {
      $roots = [
        'Drupal\\' . $extension['name'] . '\\' => '/src/',
        'Drupal\\Tests\\' . $extension['name'] . '\\' => '/tests/src/',
      ];
      foreach ($roots as $namespace => $directory) {
        if (!str_starts_with($name, $namespace)) {
          continue;
        }
        $expected = $extension['dir'] . $directory . str_replace('\\', '/', substr($name, strlen($namespace))) . '.php';
        if ((realpath($expected) ?: $expected) === (realpath($file) ?: $file)) {
          return [];
        }
        $relative = substr($expected, strlen($extension['dir']) + 1);
        return [
          RuleErrorBuilder::message(sprintf('%s must be in %s/%s to follow PSR-4.', $name, $extension['name'], $relative))
            ->identifier('drupalCs.naming.psr4')
            ->line($node->getOriginalNode()->getStartLine())
            ->build(),
        ];
      }
    }
    return [];
  }

}
