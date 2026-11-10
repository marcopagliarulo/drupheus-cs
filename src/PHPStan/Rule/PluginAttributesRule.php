<?php

declare(strict_types=1);

namespace DrupheusCs\PHPStan\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports plugins still declared with a docblock annotation.
 *
 * @implements \PHPStan\Rules\Rule<\PHPStan\Node\InClassNode>
 */
final class PluginAttributesRule implements Rule {

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
    if (!str_contains($class->getName(), '\\Plugin\\')) {
      return [];
    }

    $comment = $node->getOriginalNode()->getDocComment()?->getText() ?? '';
    if (preg_match('/^\s*\*\s*@([A-Z]\w+)\s*\(/m', $comment, $matches) !== 1) {
      return [];
    }

    return [
      RuleErrorBuilder::message(sprintf('Plugin %s uses the @%s annotation: use the #[%s] attribute instead.', $class->getName(), $matches[1], $matches[1]))
        ->identifier('drupheusCs.pluginAttributes.annotation')
        ->line($node->getOriginalNode()->getStartLine())
        ->build(),
    ];
  }

}
