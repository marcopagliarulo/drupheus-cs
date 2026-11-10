<?php

declare(strict_types=1);

namespace DrupheusCs\PHPStan\Rule;

use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Expr\YieldFrom;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Requires native parameter and return types where PHP allows adding them.
 *
 * Methods that override a parent or implement an interface are exempt, as are
 * hook implementations, because their signature is defined elsewhere.
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\FunctionLike>
 */
final class NativeTypeHintsRule implements Rule {

  private const array NO_RETURN_TYPE = ['__construct', '__destruct', '__clone'];

  /**
   * {@inheritdoc}
   */
  public function getNodeType(): string {
    return FunctionLike::class;
  }

  /**
   * {@inheritdoc}
   */
  public function processNode(Node $node, Scope $scope): array {
    if ($node instanceof ClassMethod) {
      $class = $scope->getClassReflection();
      if ($class === NULL || $this->isInherited($class, $node->name->toString())) {
        return [];
      }
      return $this->check($node, $class->getName() . '::' . $node->name->toString() . '()', $node->name->toString());
    }

    if ($node instanceof Function_) {
      $comment = $node->getDocComment()?->getText() ?? '';
      if (preg_match('/Implements\s+(hook|callback)_/', $comment) === 1) {
        return [];
      }
      return $this->check($node, $node->name->toString() . '()', $node->name->toString());
    }

    return [];
  }

  /**
   * Checks whether a method's signature is defined by an ancestor.
   */
  private function isInherited(ClassReflection $class, string $method): bool {
    if (strtolower($method) === '__construct') {
      return FALSE;
    }
    foreach ([...$class->getParents(), ...array_values($class->getInterfaces())] as $ancestor) {
      if ($ancestor->hasNativeMethod($method)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks the signature of a function or method.
   *
   * @return list<\PHPStan\Rules\IdentifierRuleError>
   *   The errors to report.
   */
  private function check(ClassMethod|Function_ $node, string $label, string $name): array {
    $errors = [];
    foreach ($node->params as $param) {
      $paramName = $param->var instanceof Variable && is_string($param->var->name) ? $param->var->name : '?';
      if ($param->type === NULL) {
        $errors[] = $this->error(sprintf('Parameter $%s of %s has no native type hint.', $paramName, $label), 'missingParameter', $param->getStartLine());
      }
      elseif ($this->isStdClass($param->type)) {
        $errors[] = $this->error(sprintf('Parameter $%s of %s must not use stdClass as a type hint.', $paramName, $label), 'stdClass', $param->getStartLine());
      }
    }

    if ($node->returnType === NULL) {
      if (!in_array(strtolower($name), self::NO_RETURN_TYPE, TRUE)) {
        $message = $this->returnsValue($node) ? ' has no native return type hint.' : ' returns nothing, so it must declare a void return type.';
        $errors[] = $this->error($label . $message, 'missingReturn', $node->getStartLine());
      }
    }
    elseif ($this->isStdClass($node->returnType)) {
      $errors[] = $this->error($label . ' must not use stdClass as a return type hint.', 'stdClass', $node->getStartLine());
    }

    return $errors;
  }

  /**
   * Checks whether a function returns or yields a value.
   *
   * Abstract methods count as returning a value, since their body is unknown.
   */
  private function returnsValue(ClassMethod|Function_ $node): bool {
    if ($node->stmts === NULL) {
      return TRUE;
    }

    $visitor = new class () extends NodeVisitorAbstract {

      /**
       * Whether a value is returned or yielded.
       */
      private bool $found = FALSE;

      /**
       * Returns whether a value is returned or yielded.
       */
      public function found(): bool {
        return $this->found;
      }

      /**
       * {@inheritdoc}
       */
      public function enterNode(Node $node): ?int {
        // Returns of nested closures and classes belong to them.
        if ($node instanceof FunctionLike || $node instanceof ClassLike) {
          return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }
        if (($node instanceof Return_ && $node->expr !== NULL) || $node instanceof Yield_ || $node instanceof YieldFrom) {
          $this->found = TRUE;
          return NodeVisitor::STOP_TRAVERSAL;
        }
        return NULL;
      }

    };
    (new NodeTraverser($visitor))->traverse($node->stmts);
    return $visitor->found();
  }

  /**
   * Checks whether a type is stdClass.
   */
  private function isStdClass(Identifier|Name|ComplexType $type): bool {
    $type = $type instanceof NullableType ? $type->type : $type;
    return $type instanceof Name && strtolower($type->toString()) === 'stdclass';
  }

  /**
   * Builds a type hint error.
   */
  private function error(string $message, string $id, int $line): IdentifierRuleError {
    return RuleErrorBuilder::message($message)->identifier('drupheusCs.types.' . $id)->line($line)->build();
  }

}
