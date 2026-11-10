<?php

declare(strict_types=1);

namespace DrupalCS\PHPStan\Rule;

use DrupalCS\PHPStan\Drupal\ConstructorAutowireAnalyzer;
use DrupalCS\PHPStan\Drupal\CoreVersion;
use DrupalCS\PHPStan\Drupal\Feature;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports where create() factories can be replaced by autowiring.
 *
 * Also validates the constructors of classes that already rely on autowiring,
 * which would otherwise only fail at runtime.
 *
 * @implements \PHPStan\Rules\Rule<\PHPStan\Node\InClassNode>
 */
final class FactoryAutowireRule implements Rule {

  private const string INJECTION = 'Drupal\Core\DependencyInjection\ContainerInjectionInterface';

  private const string PLUGIN_FACTORY = 'Drupal\Core\Plugin\ContainerFactoryPluginInterface';

  private const string AUTOWIRE_TRAIT = 'Drupal\Core\DependencyInjection\AutowireTrait';

  private const string HOOK_ATTRIBUTE = 'Drupal\Core\Hook\Attribute\Hook';

  private const array PLUGIN_ARGUMENTS = ['configuration', 'plugin_id', 'plugin_definition'];

  /**
   * Constructs a FactoryAutowireRule object.
   */
  public function __construct(
    private readonly ConstructorAutowireAnalyzer $analyzer,
    private readonly CoreVersion $coreVersion,
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
    $class = $node->getClassReflection();
    $statement = $node->getOriginalNode();
    if (!$statement instanceof Class_ || $class->isAbstract() || $class->isAnonymous()) {
      return [];
    }

    $file = $scope->getFile();
    $create = $statement->getMethod('create');
    if ($create !== NULL && $this->isFactoryClass($class)) {
      return $this->analyzeCreate($class, $statement, $create, $file);
    }
    if ($create === NULL && $this->reliesOnAutowiring($class, $statement, $file)) {
      return $this->validateConstructor($class, $statement);
    }
    return [];
  }

  /**
   * Checks whether a class is built through a container factory.
   */
  private function isFactoryClass(ClassReflection $class): bool {
    return $class->implementsInterface(self::INJECTION) || $class->implementsInterface(self::PLUGIN_FACTORY);
  }

  /**
   * Checks whether a class without create() gets autowired by Drupal.
   */
  private function reliesOnAutowiring(ClassReflection $class, Class_ $statement, string $file): bool {
    if ($class->hasTraitUse(self::AUTOWIRE_TRAIT) || $this->hasHookAttribute($statement)) {
      return TRUE;
    }
    return $this->inheritedAutowire($class, $file) !== NULL;
  }

  /**
   * Checks whether a class or one of its methods has #[Hook].
   */
  private function hasHookAttribute(Class_ $statement): bool {
    foreach ([$statement, ...$statement->getMethods()] as $node) {
      foreach ($node->attrGroups as $group) {
        foreach ($group->attrs as $attribute) {
          if ($attribute->name->toString() === self::HOOK_ATTRIBUTE) {
            return TRUE;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Returns the base class whose inherited create() autowires, if any.
   *
   * A base such as ConfigFormBase overrides create() without autowiring, so
   * the declaring class has to be checked rather than the class hierarchy.
   */
  private function inheritedAutowire(ClassReflection $class, string $file): ?string {
    $parent = $class->getParentClass();
    if ($parent === NULL || !$parent->hasNativeMethod('create')) {
      return NULL;
    }

    $declaring = $parent->getNativeMethod('create')->getDeclaringClass()->getName();
    return match (TRUE) {
      $declaring === 'Drupal\Core\Controller\ControllerBase' => $declaring,
      $declaring === 'Drupal\Core\Form\FormBase' && $this->coreVersion->supports($file, Feature::FORM_AUTOWIRE) => $declaring,
      $declaring === 'Drupal\Core\Plugin\PluginBase' && $class->implementsInterface(self::PLUGIN_FACTORY) && $this->coreVersion->supports($file, Feature::PLUGIN_AUTOWIRE) => $declaring,
      default => NULL,
    };
  }

  /**
   * Reports whether an existing create() can be replaced by autowiring.
   *
   * @return list<\PHPStan\Rules\IdentifierRuleError>
   *   The errors to report.
   */
  private function analyzeCreate(ClassReflection $class, Class_ $statement, ClassMethod $create, string $file): array {
    $isPlugin = $class->implementsInterface(self::PLUGIN_FACTORY);
    if ($isPlugin && !$class->is('Drupal\Core\Plugin\PluginBase')) {
      return $this->unavailable($class, 'plugins can only autowire when they extend \Drupal\Core\Plugin\PluginBase', $statement);
    }
    if ($isPlugin && !$this->coreVersion->supports($file, Feature::PLUGIN_AUTOWIRE)) {
      return $this->unavailable($class, sprintf('plugin autowiring needs core %s; this code supports %s', Feature::PLUGIN_AUTOWIRE, $this->coreVersion->minimumFor($file)), $statement);
    }

    $inherited = $this->inheritedAutowire($class, $file);
    if ($isPlugin && $inherited === NULL) {
      return $this->unavailable($class, 'a parent class overrides create() without autowiring', $statement);
    }

    $arguments = $this->factoryArguments($create);
    if ($arguments === NULL) {
      return $this->unavailable($class, 'create() does more than pass services to the constructor', $statement);
    }

    $constructor = $statement->getMethod('__construct');
    if ($constructor === NULL) {
      return $this->unavailable($class, 'the constructor is inherited, so its services cannot be checked', $statement);
    }

    $params = $constructor->params;
    if ($isPlugin) {
      if (!$this->passesPluginArguments($arguments)) {
        return $this->unavailable($class, 'create() does not pass $configuration, $plugin_id and $plugin_definition first', $statement);
      }
      $arguments = array_slice($arguments, count(self::PLUGIN_ARGUMENTS));
      $params = array_slice($params, count(self::PLUGIN_ARGUMENTS));
    }

    if (count($arguments) !== count($params)) {
      return $this->unavailable($class, 'create() does not supply every constructor parameter', $statement);
    }

    $containerVariable = $this->containerVariable($create);
    $attributes = [];
    foreach ($arguments as $index => $argument) {
      $resolved = $this->resolveArgument($argument, $params[$index], $containerVariable);
      if ($resolved === FALSE) {
        return $this->unavailable($class, sprintf('constructor parameter $%s is not a plain service or container parameter', $this->analyzer->parameterName($params[$index])), $statement);
      }
      if ($resolved !== NULL) {
        $attributes[] = sprintf('%s on $%s', $resolved, $this->analyzer->parameterName($params[$index]));
      }
    }

    $action = $inherited !== NULL ? 'delete create()' : 'replace create() with `use AutowireTrait;`';
    $message = sprintf('%s can use autowiring: %s', $class->getName(), $action);
    if ($attributes !== []) {
      $message .= ' and add ' . implode(', ', $attributes);
    }

    return [
      RuleErrorBuilder::message($message . '.')
        ->identifier('drupalCs.autowire.available')
        ->line($statement->getStartLine())
        ->build(),
    ];
  }

  /**
   * Validates the constructor of a class that relies on autowiring.
   *
   * @return list<\PHPStan\Rules\IdentifierRuleError>
   *   One error per parameter that would fail at runtime.
   */
  private function validateConstructor(ClassReflection $class, Class_ $statement): array {
    $constructor = $statement->getMethod('__construct');
    if ($constructor === NULL) {
      return [];
    }

    $params = $constructor->params;
    if ($class->implementsInterface(self::PLUGIN_FACTORY)) {
      $params = array_slice($params, count(self::PLUGIN_ARGUMENTS));
    }

    return array_map(
      static fn(string $problem): IdentifierRuleError => RuleErrorBuilder::message(sprintf('%s cannot be autowired: %s.', $class->getName(), $problem))
        ->identifier('drupalCs.autowire.unresolvable')
        ->line($constructor->getStartLine())
        ->build(),
      $this->analyzer->problems($params, FALSE),
    );
  }

  /**
   * Returns the arguments of a create() that only instantiates the class.
   *
   * @return list<\PhpParser\Node\Expr>|null
   *   The constructor arguments, or NULL if create() does anything else.
   */
  private function factoryArguments(ClassMethod $create): ?array {
    if (count($create->stmts ?? []) !== 1 || !$create->stmts[0] instanceof Return_) {
      return NULL;
    }
    $expression = $create->stmts[0]->expr;
    if (!$expression instanceof New_ || !$expression->class instanceof Name) {
      return NULL;
    }
    if (!in_array($expression->class->toLowerString(), ['static', 'self'], TRUE)) {
      return NULL;
    }

    $arguments = [];
    foreach ($expression->args as $arg) {
      if (!$arg instanceof Arg || $arg->unpack || $arg->name !== NULL) {
        return NULL;
      }
      $arguments[] = $arg->value;
    }
    return $arguments;
  }

  /**
   * Checks that arguments start with the three standard plugin variables.
   *
   * @param list<\PhpParser\Node\Expr> $arguments
   *   The constructor arguments.
   */
  private function passesPluginArguments(array $arguments): bool {
    foreach (self::PLUGIN_ARGUMENTS as $index => $name) {
      $argument = $arguments[$index] ?? NULL;
      $camelCase = lcfirst(str_replace('_', '', ucwords($name, '_')));
      if (!$argument instanceof Variable || !in_array($argument->name, [$name, $camelCase], TRUE)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Returns the name of the $container parameter of create().
   */
  private function containerVariable(ClassMethod $create): string {
    $param = $create->params[0] ?? NULL;
    return $param instanceof Param ? $this->analyzer->parameterName($param) : 'container';
  }

  /**
   * Maps a create() argument to the attribute its parameter needs.
   *
   * @return string|false|null
   *   The attribute source, NULL when the type is enough, or FALSE when the
   *   argument cannot be expressed through autowiring.
   */
  private function resolveArgument(Expr $argument, Param $param, string $containerVariable): string|false|null {
    if (!$argument instanceof MethodCall || !$argument->name instanceof Identifier) {
      return FALSE;
    }
    if (!$argument->var instanceof Variable || $argument->var->name !== $containerVariable) {
      return FALSE;
    }
    $firstArgument = $argument->getArgs()[0]->value ?? NULL;
    if (!$firstArgument instanceof String_) {
      return FALSE;
    }

    return match ($argument->name->toLowerString()) {
      'get' => $this->analyzer->attributeFor($param, $firstArgument->value),
      'getparameter' => sprintf("#[Autowire(param: '%s')]", $firstArgument->value),
      default => FALSE,
    };
  }

  /**
   * Builds the optional report for classes that cannot use autowiring.
   *
   * @return list<\PHPStan\Rules\IdentifierRuleError>
   *   One error in report mode, otherwise none.
   */
  private function unavailable(ClassReflection $class, string $reason, Class_ $statement): array {
    if (!$this->reportUnavailable) {
      return [];
    }
    return [
      RuleErrorBuilder::message(sprintf('%s cannot use autowiring: %s.', $class->getName(), $reason))
        ->identifier('drupalCs.autowire.unavailable')
        ->line($statement->getStartLine())
        ->build(),
    ];
  }

}
