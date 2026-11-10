<?php

declare(strict_types=1);

namespace DrupalCS\PHPStan\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces one naming style for local variables and parameters.
 *
 * Parameters whose name comes from a parent method or a hook definition are
 * exempt, such as $form_state in FormInterface::buildForm().
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\FunctionLike>
 */
final class VariableCaseRule implements Rule {

  private const string SNAKE_CASE = 'snake_case';

  private const string HOOK_ATTRIBUTE = 'Drupal\Core\Hook\Attribute\Hook';

  /**
   * Constructs a VariableCaseRule object.
   */
  public function __construct(
    private readonly string $style,
  ) {}

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
    if (!$node instanceof ClassMethod && !$node instanceof Function_) {
      return [];
    }

    $exempt = $this->exemptParameters($node, $scope->getClassReflection());
    $label = $node instanceof ClassMethod && $scope->getClassReflection() !== NULL
      ? $scope->getClassReflection()->getName() . '::' . $node->name->toString() . '()'
      : $node->name->toString() . '()';

    $variables = (new NodeFinder())->findInstanceOf([...$node->params, ...($node->stmts ?? [])], Variable::class);
    $reported = [];
    $errors = [];
    foreach ($variables as $variable) {
      $name = $variable->name;
      if (!is_string($name) || isset($exempt[$name]) || isset($reported[$name]) || $this->isValid($name)) {
        continue;
      }

      $reported[$name] = TRUE;
      $errors[] = RuleErrorBuilder::message(sprintf('Variable $%s in %s must use %s: rename it to $%s.', $name, $label, $this->style, $this->convert($name)))
        ->identifier('drupalCs.naming.variableCase')
        ->line($variable->getStartLine())
        ->build();
    }

    return $errors;
  }

  /**
   * Returns the parameters whose names this function does not control.
   *
   * @return array<string, true>
   *   The exempt parameter names.
   */
  private function exemptParameters(ClassMethod|Function_ $node, ?ClassReflection $class): array {
    if ($this->implementsHook($node)) {
      return array_fill_keys(array_map($this->parameterName(...), $node->params), TRUE);
    }

    $exempt = [];
    if ($this->style === self::SNAKE_CASE) {
      // A promoted parameter is also a property, which must be lowerCamel.
      foreach ($node->params as $param) {
        if ($param->flags !== 0) {
          $exempt[$this->parameterName($param)] = TRUE;
        }
      }
    }

    if ($node instanceof ClassMethod && $class !== NULL) {
      $method = $node->name->toString();
      foreach ([...$class->getParents(), ...array_values($class->getInterfaces())] as $ancestor) {
        if (!$ancestor->hasNativeMethod($method)) {
          continue;
        }
        foreach ($ancestor->getNativeMethod($method)->getVariants() as $variant) {
          foreach ($variant->getParameters() as $parameter) {
            $exempt[$parameter->getName()] = TRUE;
          }
        }
      }
    }

    return $exempt;
  }

  /**
   * Checks whether a function or method implements a hook or callback.
   */
  private function implementsHook(ClassMethod|Function_ $node): bool {
    if (preg_match('/Implements\s+(hook|callback)_/', $node->getDocComment()?->getText() ?? '') === 1) {
      return TRUE;
    }
    foreach ($node->attrGroups as $group) {
      foreach ($group->attrs as $attribute) {
        if ($attribute->name->toString() === self::HOOK_ATTRIBUTE) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Returns the name of a parameter.
   */
  private function parameterName(Param $param): string {
    return $param->var instanceof Variable && is_string($param->var->name) ? $param->var->name : '';
  }

  /**
   * Checks whether a variable name follows the configured style.
   */
  private function isValid(string $name): bool {
    // $this, $GLOBALS and superglobals such as $_GET are not ours to name.
    if ($name === 'this' || $name === 'GLOBALS' || str_starts_with($name, '_')) {
      return TRUE;
    }
    $pattern = $this->style === self::SNAKE_CASE ? '/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/' : '/^[a-z][a-zA-Z0-9]*$/';
    return preg_match($pattern, $name) === 1;
  }

  /**
   * Converts a variable name to the configured style.
   */
  private function convert(string $name): string {
    if ($this->style === self::SNAKE_CASE) {
      return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
    return lcfirst(str_replace('_', '', ucwords($name, '_')));
  }

}
