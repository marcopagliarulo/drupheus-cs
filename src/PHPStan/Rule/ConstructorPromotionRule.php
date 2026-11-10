<?php

declare(strict_types=1);

namespace DrupalCS\PHPStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports properties that can be promoted to constructor parameters.
 *
 * SlevomatCodingStandard's equivalent sniff skips properties with a described
 * docblock and requires identical names, which rules out Drupal's standards.
 *
 * @implements \PHPStan\Rules\Rule<\PHPStan\Node\InClassNode>
 */
final class ConstructorPromotionRule implements Rule {

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
    $statement = $node->getOriginalNode();
    $constructor = $statement instanceof Class_ ? $statement->getMethod('__construct') : NULL;
    if ($constructor === NULL || $constructor->stmts === NULL) {
      return [];
    }

    $params = $this->promotableParameters($constructor->params);
    $properties = $this->promotableProperties($statement);
    $finder = new NodeFinder();
    $errors = [];

    foreach ($constructor->stmts as $index => $bodyStatement) {
      $assignment = $this->propertyAssignment($bodyStatement);
      if ($assignment === NULL || !isset($params[$assignment['param']], $properties[$assignment['property']])) {
        continue;
      }

      $param = $params[$assignment['param']];
      $property = $properties[$assignment['property']];
      $before = array_slice($constructor->stmts, 0, $index);
      if ($this->isReassigned($finder, $before, $assignment['param']) || !$this->typesMatch($param, $property)) {
        continue;
      }

      $errors[] = RuleErrorBuilder::message(sprintf('Property %s::$%s can be promoted to the constructor.', $node->getClassReflection()->getName(), $assignment['property']))
        ->identifier('drupalCs.constructorPromotion.available')
        ->line($property->getStartLine())
        ->build();
    }

    return $errors;
  }

  /**
   * Returns constructor parameters that could become properties, by name.
   *
   * @param array<\PhpParser\Node\Param> $params
   *   The constructor parameters.
   *
   * @return array<string, \PhpParser\Node\Param>
   *   Parameters that are not promoted or variadic.
   */
  private function promotableParameters(array $params): array {
    $promotable = [];
    foreach ($params as $param) {
      if ($param->flags === 0 && !$param->variadic && $param->var instanceof Variable && is_string($param->var->name)) {
        $promotable[$param->var->name] = $param;
      }
    }
    return $promotable;
  }

  /**
   * Returns properties that could be declared by the constructor, by name.
   *
   * @return array<string, \PhpParser\Node\Stmt\Property>
   *   Instance properties with no default value or attributes.
   */
  private function promotableProperties(Class_ $class): array {
    $promotable = [];
    foreach ($class->getProperties() as $property) {
      if ($property->isStatic() || $property->attrGroups !== [] || count($property->props) !== 1) {
        continue;
      }
      if ($property->props[0]->default === NULL) {
        $promotable[$property->props[0]->name->toString()] = $property;
      }
    }
    return $promotable;
  }

  /**
   * Returns the names in a "$this->property = $param;" statement.
   *
   * @return array{property: string, param: string}|null
   *   The names, or NULL if the statement is anything else.
   */
  private function propertyAssignment(Node $statement): ?array {
    if (!$statement instanceof Expression || !$statement->expr instanceof Assign) {
      return NULL;
    }
    $target = $statement->expr->var;
    $value = $statement->expr->expr;
    if (!$target instanceof PropertyFetch || !$target->var instanceof Variable || $target->var->name !== 'this') {
      return NULL;
    }
    if (!$target->name instanceof Identifier || !$value instanceof Variable || !is_string($value->name)) {
      return NULL;
    }
    return ['property' => $target->name->toString(), 'param' => $value->name];
  }

  /**
   * Checks whether statements assign a new value to a variable.
   *
   * @param \PhpParser\NodeFinder $finder
   *   The node finder.
   * @param array<\PhpParser\Node\Stmt> $statements
   *   The statements to search.
   * @param string $variable
   *   The variable name.
   */
  private function isReassigned(NodeFinder $finder, array $statements, string $variable): bool {
    return $finder->findFirst($statements, static fn(Node $node): bool => $node instanceof Assign && $node->var instanceof Variable && $node->var->name === $variable) !== NULL;
  }

  /**
   * Checks that a parameter and property declare compatible types.
   */
  private function typesMatch(Param $param, Property $property): bool {
    return $property->type === NULL || $this->typeString($param->type) === $this->typeString($property->type);
  }

  /**
   * Returns a comparable form of a type.
   */
  private function typeString(?Node $type): string {
    return match (TRUE) {
      $type instanceof NullableType => '?' . $this->typeString($type->type),
      $type instanceof Name, $type instanceof Identifier => strtolower($type->toString()),
      $type instanceof UnionType, $type instanceof IntersectionType => implode('|', array_map($this->typeString(...), $type->types)),
      default => '',
    };
  }

}
