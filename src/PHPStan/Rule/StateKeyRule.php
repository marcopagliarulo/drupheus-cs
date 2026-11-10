<?php

declare(strict_types=1);

namespace DrupheusCs\PHPStan\Rule;

use DrupheusCs\PHPStan\Drupal\ExtensionLocator;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Requires lowercase State API keys prefixed with the extension name.
 *
 * The coding standards name this rule for variable_set(), which the State API
 * replaced. A dotted key such as system.cron_last belongs to the extension
 * before the dot, so writing another extension's key is allowed.
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\MethodCall>
 */
final class StateKeyRule implements Rule {

  private const string STATE = 'Drupal\Core\State\StateInterface';

  /**
   * Constructs a StateKeyRule object.
   */
  public function __construct(
    private readonly ExtensionLocator $extensions,
  ) {}

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
    if ($method !== 'set' && $method !== 'setmultiple') {
      return [];
    }
    if (!(new ObjectType(self::STATE))->isSuperTypeOf($scope->getType($node->var))->yes()) {
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

    $prefixes = array_column($this->extensions->extensionsFor($scope->getFile()), 'name');
    $errors = [];
    foreach ($keys as $key) {
      $message = $this->check($key->getValue(), $prefixes);
      if ($message !== NULL) {
        $errors[] = RuleErrorBuilder::message($message)
          ->identifier('drupheusCs.naming.stateKey')
          ->build();
      }
    }
    return $errors;
  }

  /**
   * Returns the problem with a key, or NULL if it is valid.
   *
   * @param string $key
   *   The state key.
   * @param list<string> $prefixes
   *   The names of the extensions containing the file.
   */
  private function check(string $key, array $prefixes): ?string {
    if (preg_match('/^[a-z0-9_.]+$/', $key) !== 1) {
      return sprintf('State key "%s" must use lowercase letters, with words separated by underscores.', $key);
    }
    if ($prefixes === [] || str_contains($key, '.')) {
      return NULL;
    }
    foreach ($prefixes as $prefix) {
      if (str_starts_with($key, $prefix . '_')) {
        return NULL;
      }
    }
    return sprintf('State key "%s" must start with the extension name, such as "%s.%s".', $key, $prefixes[0], $key);
  }

}
