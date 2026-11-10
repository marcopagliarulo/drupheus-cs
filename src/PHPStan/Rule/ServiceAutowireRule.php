<?php

declare(strict_types=1);

namespace DrupheusCs\PHPStan\Rule;

use DrupheusCs\PHPStan\Drupal\ConstructorAutowireAnalyzer;
use DrupheusCs\PHPStan\Drupal\ExtensionLocator;
use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Checks services.yml definitions against autowiring.
 *
 * Errors are attached to the service class so they can be ignored like any
 * other PHPStan error.
 *
 * @implements \PHPStan\Rules\Rule<\PHPStan\Node\InClassNode>
 */
final class ServiceAutowireRule implements Rule {

  /**
   * Constructs a ServiceAutowireRule object.
   */
  public function __construct(
    private readonly ConstructorAutowireAnalyzer $analyzer,
    private readonly ExtensionLocator $locator,
    private readonly bool $reportUnavailable,
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
    $statement = $node->getOriginalNode();
    if (!$statement instanceof Class_) {
      return [];
    }
    $extension = $this->locator->extensionsFor($scope->getFile())[0] ?? NULL;
    if ($extension === NULL) {
      return [];
    }

    $file = $this->locator->services($extension['dir'], $extension['name']);
    $services = $file['services'] ?? [];
    if (!is_array($services)) {
      return [];
    }

    $className = $node->getClassReflection()->getName();
    $defaults = is_array($services['_defaults'] ?? NULL) ? $services['_defaults'] : [];
    $errors = [];
    foreach ($services as $id => $definition) {
      if ($id === '_defaults' || !is_array($definition) || ($definition['class'] ?? $id) !== $className) {
        continue;
      }
      if (isset($definition['parent']) || isset($definition['factory']) || isset($definition['alias'])) {
        continue;
      }
      array_push($errors, ...$this->analyzeDefinition((string) $id, $definition, $defaults, $statement, $extension['name']));
    }
    return $errors;
  }

  /**
   * Analyzes one service definition.
   *
   * @param string $id
   *   The service ID.
   * @param array<mixed> $definition
   *   The service definition.
   * @param array<mixed> $defaults
   *   The file's _defaults section.
   * @param \PhpParser\Node\Stmt\Class_ $statement
   *   The service class.
   * @param string $extension
   *   The name of the extension defining the service.
   *
   * @return list<\PHPStan\Rules\IdentifierRuleError>
   *   The errors to report.
   */
  private function analyzeDefinition(string $id, array $definition, array $defaults, Class_ $statement, string $extension): array {
    $constructor = $statement->getMethod('__construct');
    $arguments = is_array($definition['arguments'] ?? NULL) ? $definition['arguments'] : [];
    $line = $statement->getStartLine();

    if ($definition['autowire'] ?? $defaults['autowire'] ?? FALSE) {
      return $constructor === NULL ? [] : $this->validateAutowired($id, $constructor->params, $arguments, $extension, $constructor->getStartLine());
    }
    if ($arguments === []) {
      return [];
    }
    if ($constructor === NULL) {
      return $this->unavailable($id, 'the constructor is inherited, so its services cannot be checked', $line, $extension);
    }
    return $this->suggestAutowire($id, $constructor->params, $arguments, $line, $extension);
  }

  /**
   * Finds parameters of an autowired service the container cannot resolve.
   *
   * @param string $id
   *   The service ID.
   * @param array<\PhpParser\Node\Param> $params
   *   The constructor parameters.
   * @param array<mixed> $arguments
   *   Explicit arguments, which take precedence over autowiring.
   * @param string $extension
   *   The name of the extension defining the service.
   * @param int $line
   *   The line to report on.
   *
   * @return list<\PHPStan\Rules\IdentifierRuleError>
   *   One error per unresolvable parameter.
   */
  private function validateAutowired(string $id, array $params, array $arguments, string $extension, int $line): array {
    $unset = [];
    foreach ($params as $index => $param) {
      if (!array_key_exists($index, $arguments) && !array_key_exists('$' . $this->analyzer->parameterName($param), $arguments)) {
        $unset[] = $param;
      }
    }

    return array_map(
      static fn(string $problem): IdentifierRuleError => RuleErrorBuilder::message(sprintf("Service '%s' in %s.services.yml cannot be autowired: %s.", $id, $extension, $problem))
        ->identifier('drupheusCs.autowire.unresolvable')
        ->line($line)
        ->build(),
      $this->analyzer->problems($unset, TRUE),
    );
  }

  /**
   * Reports whether explicit arguments can be replaced by autowire: true.
   *
   * @param string $id
   *   The service ID.
   * @param array<\PhpParser\Node\Param> $params
   *   The constructor parameters.
   * @param array<mixed> $arguments
   *   The explicit arguments.
   * @param int $line
   *   The line to report on.
   * @param string $extension
   *   The name of the extension defining the service.
   *
   * @return list<\PHPStan\Rules\IdentifierRuleError>
   *   The errors to report.
   */
  private function suggestAutowire(string $id, array $params, array $arguments, int $line, string $extension): array {
    if (!array_is_list($arguments) || count($arguments) !== count($params)) {
      return $this->unavailable($id, 'its arguments do not map one-to-one onto the constructor', $line, $extension);
    }

    $attributes = [];
    foreach ($arguments as $index => $argument) {
      $resolved = $this->resolveArgument($argument, $params[$index]);
      if ($resolved === FALSE) {
        return $this->unavailable($id, sprintf('argument $%s is not a plain service or container parameter', $this->analyzer->parameterName($params[$index])), $line, $extension);
      }
      if ($resolved !== NULL) {
        $attributes[] = sprintf('%s on $%s', $resolved, $this->analyzer->parameterName($params[$index]));
      }
    }

    $message = sprintf("Service '%s' in %s.services.yml can use `autowire: true` instead of arguments", $id, $extension);
    if ($attributes !== []) {
      $message .= ' with ' . implode(', ', $attributes);
    }

    return [
      RuleErrorBuilder::message($message . '.')
        ->identifier('drupheusCs.autowire.available')
        ->line($line)
        ->build(),
    ];
  }

  /**
   * Maps a YAML argument to the attribute its parameter needs.
   *
   * @return string|false|null
   *   The attribute source, NULL when the type is enough, or FALSE when the
   *   argument cannot be expressed through autowiring.
   */
  private function resolveArgument(mixed $argument, Param $param): string|false|null {
    if (!is_string($argument)) {
      return FALSE;
    }
    if (preg_match('/^%([^%]+)%$/', $argument, $matches) === 1) {
      return sprintf("#[Autowire(param: '%s')]", $matches[1]);
    }
    if (!str_starts_with($argument, '@') || str_starts_with($argument, '@?') || str_starts_with($argument, '@@')) {
      return FALSE;
    }
    return $this->analyzer->attributeFor($param, substr($argument, 1));
  }

  /**
   * Builds the optional report for services that cannot use autowiring.
   *
   * @return list<\PHPStan\Rules\IdentifierRuleError>
   *   One error in report mode, otherwise none.
   */
  private function unavailable(string $id, string $reason, int $line, string $extension): array {
    if (!$this->reportUnavailable) {
      return [];
    }
    return [
      RuleErrorBuilder::message(sprintf("Service '%s' in %s.services.yml cannot use autowiring: %s.", $id, $extension, $reason))
        ->identifier('drupheusCs.autowire.unavailable')
        ->line($line)
        ->build(),
    ];
  }

}
