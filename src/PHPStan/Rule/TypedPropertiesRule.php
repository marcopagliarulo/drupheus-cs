<?php

declare(strict_types=1);

namespace DrupheusCs\PHPStan\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassPropertyNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Requires native types on properties a parent class does not declare.
 *
 * @implements \PHPStan\Rules\Rule<\PHPStan\Node\ClassPropertyNode>
 */
final class TypedPropertiesRule implements Rule {

  /**
   * {@inheritdoc}
   */
  public function getNodeType(): string {
    return ClassPropertyNode::class;
  }

  /**
   * {@inheritdoc}
   */
  public function processNode(Node $node, Scope $scope): array {
    if ($node->getNativeType() !== NULL) {
      return [];
    }

    $class = $node->getClassReflection();
    $name = $node->getName();
    if ($this->isInherited($class, $name)) {
      return [];
    }

    return [
      RuleErrorBuilder::message(sprintf('Property %s::$%s has no native type.', $class->getName(), $name))
        ->identifier('drupheusCs.types.missingProperty')
        ->line($node->getStartLine())
        ->build(),
    ];
  }

  /**
   * Checks whether an ancestor fixes the declaration of a property.
   *
   * PHP forbids adding a type to a property inherited without one.
   */
  private function isInherited(ClassReflection $class, string $name): bool {
    foreach ($class->getParents() as $parent) {
      if ($parent->hasNativeProperty($name)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
