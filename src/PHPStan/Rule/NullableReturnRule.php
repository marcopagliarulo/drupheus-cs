<?php

declare(strict_types=1);

namespace DrupalCS\PHPStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;

/**
 * Requires a nullable return type when a function can return NULL.
 *
 * PHPStan only reports this from level 8, together with every call on a
 * nullable value. Returning nothing but NULL is already reported at level 3.
 * NULL must be possible by both the native and the documented type: many
 * Drupal APIs document a NULL that a caller often knows cannot happen, such as
 * EntityInterface::id(), and some PHP signatures still include a NULL that
 * PHPStan knows a call cannot return, such as version_compare() with an
 * operator.
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Stmt\Return_>
 */
final class NullableReturnRule implements Rule {

  /**
   * {@inheritdoc}
   */
  public function getNodeType(): string {
    return Return_::class;
  }

  /**
   * {@inheritdoc}
   */
  public function processNode(Node $node, Scope $scope): array {
    $function = $scope->getFunction();
    if ($node->expr === NULL || $function === NULL || $scope->isInAnonymousFunction()) {
      return [];
    }

    $declared = $function->getNativeReturnType();
    if ($declared instanceof MixedType || $declared->accepts(new NullType(), TRUE)->yes()) {
      return [];
    }
    $type = $scope->getType($node->expr);
    if ($type->isNull()->yes() || !TypeCombinator::containsNull($type) || !TypeCombinator::containsNull($scope->getNativeType($node->expr))) {
      return [];
    }

    $name = $function->getName() . '()';
    $class = $scope->getClassReflection();
    if ($class !== NULL) {
      $name = $class->getName() . '::' . $name;
    }
    $described = $declared->describe(VerbosityLevel::typeOnly());
    $nullable = str_contains($described, '|') ? $described . '|null' : '?' . $described;
    return [
      RuleErrorBuilder::message(sprintf('%s can return NULL, so its return type must be nullable: %s.', $name, $nullable))
        ->identifier('drupalCs.types.nullable')
        ->build(),
    ];
  }

}
