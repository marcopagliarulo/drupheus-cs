<?php

declare(strict_types=1);

namespace DrupheusCs\PHPStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Requires request attributes set by code to start with "_".
 *
 * Only values from the path, such as {node} in /node/{node}, omit it, and
 * the router sets those.
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\MethodCall>
 */
final class RequestAttributeRule implements Rule {

  private const string REQUEST = 'Symfony\Component\HttpFoundation\Request';

  /**
   * {@inheritdoc}
   */
  public function getNodeType(): string {
    return MethodCall::class;
  }

  /**
   * {@inheritdoc}
   */
  public function processNode(Node $node, Scope $scope): array {
    if (!$node->name instanceof Identifier || !isset($node->getArgs()[0])) {
      return [];
    }
    $method = $node->name->toLowerString();
    if ($method !== 'set' && $method !== 'add') {
      return [];
    }
    $bag = $node->var;
    if (!$bag instanceof PropertyFetch || !$bag->name instanceof Identifier || $bag->name->toString() !== 'attributes') {
      return [];
    }
    if (!(new ObjectType(self::REQUEST))->isSuperTypeOf($scope->getType($bag->var))->yes()) {
      return [];
    }

    $type = $scope->getType($node->getArgs()[0]->value);
    $keys = [];
    if ($method === 'set') {
      $keys = $type->getConstantStrings();
    }
    else {
      foreach ($type->getConstantArrays() as $array) {
        array_push($keys, ...$array->getKeyType()->getConstantStrings());
      }
    }

    $errors = [];
    foreach ($keys as $key) {
      if (!str_starts_with($key->getValue(), '_')) {
        $errors[] = RuleErrorBuilder::message(sprintf('Request attribute "%1$s" must start with "_", such as "_%1$s": only values from the path omit it.', $key->getValue()))
          ->identifier('drupheusCs.naming.requestAttribute')
          ->build();
      }
    }
    return $errors;
  }

}
