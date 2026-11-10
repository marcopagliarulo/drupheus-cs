<?php

declare(strict_types=1);

namespace DrupheusCs\PHPStan\Drupal;

use mglaman\PHPStanDrupal\Drupal\ServiceMap;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Expr\Variable;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;

/**
 * Works out how constructor parameters map onto autowired services.
 */
final class ConstructorAutowireAnalyzer {

  private const string AUTOWIRE_ATTRIBUTE = 'Symfony\Component\DependencyInjection\Attribute\Autowire';

  private const int MAX_CANDIDATES = 3;

  /**
   * Constructs a ConstructorAutowireAnalyzer object.
   */
  public function __construct(
    private readonly ServiceMap $serviceMap,
    private readonly ReflectionProvider $reflectionProvider,
  ) {}

  /**
   * Returns why each constructor parameter cannot be autowired.
   *
   * @param array<\PhpParser\Node\Param> $params
   *   The constructor parameters to check.
   * @param bool $defaultsAllowed
   *   Whether parameters with a default value may stay unresolved. The
   *   container compiler allows it; AutowiredInstanceTrait does not.
   *
   * @return list<string>
   *   One problem description per parameter that cannot be resolved.
   */
  public function problems(array $params, bool $defaultsAllowed): array {
    $problems = [];
    foreach ($params as $param) {
      $problem = $this->problem($param, $defaultsAllowed);
      if ($problem !== NULL) {
        $problems[] = $problem;
      }
    }
    return $problems;
  }

  /**
   * Returns the attribute a parameter needs to receive a known service.
   *
   * @return string|null
   *   The attribute source, or NULL when the type alone resolves to the
   *   service.
   */
  public function attributeFor(Param $param, string $serviceId): ?string {
    $type = $this->typeName($param);
    if ($type !== NULL && $this->sameService($type, $serviceId)) {
      return NULL;
    }
    return sprintf("#[Autowire(service: '%s')]", $serviceId);
  }

  /**
   * Checks whether a service ID exists.
   */
  public function serviceExists(string $id): bool {
    return $this->serviceMap->getService($id) !== NULL;
  }

  /**
   * Returns the name of a parameter.
   */
  public function parameterName(Param $param): string {
    return $param->var instanceof Variable && is_string($param->var->name) ? $param->var->name : '?';
  }

  /**
   * Returns why a single parameter cannot be resolved, if it cannot.
   */
  private function problem(Param $param, bool $defaultsAllowed): ?string {
    $name = '$' . $this->parameterName($param);

    if ($this->hasAutowireFamilyAttribute($param)) {
      $service = $this->attributeService($param);
      if ($service !== NULL && !$this->serviceExists($service)) {
        return sprintf("%s: #[Autowire] names the unknown service '%s'", $name, $service);
      }
      return NULL;
    }

    if ($param->default !== NULL && $defaultsAllowed) {
      return NULL;
    }

    $type = $param->type;
    if ($type === NULL || $type instanceof NullableType) {
      return NULL;
    }
    if ($type instanceof Identifier) {
      return sprintf('%s: built-in type %s needs #[Autowire]', $name, $type->toString());
    }
    if (!$type instanceof Name) {
      // Union and intersection types are never resolved by type.
      return sprintf('%s: union and intersection types need #[Autowire]', $name);
    }

    $typeName = $type->toString();
    if ($this->serviceExists($typeName)) {
      return NULL;
    }

    $candidates = $this->candidates($typeName);
    if ($candidates === []) {
      return sprintf('%s: no service is aliased to %s', $name, $typeName);
    }
    return sprintf(
      "%s: no service is aliased to %s; add #[Autowire(service: '...')] naming one of its services, for example %s",
      $name,
      $typeName,
      implode(', ', $candidates),
    );
  }

  /**
   * Returns the class name of a parameter's type, without nullability.
   */
  private function typeName(Param $param): ?string {
    $type = $param->type instanceof NullableType ? $param->type->type : $param->type;
    return $type instanceof Name ? $type->toString() : NULL;
  }

  /**
   * Checks whether a type alias and a service ID are the same service.
   */
  private function sameService(string $type, string $serviceId): bool {
    if ($type === $serviceId) {
      return TRUE;
    }
    $alias = $this->serviceMap->getService($type);
    $service = $this->serviceMap->getService($serviceId);
    return $alias !== NULL && $service !== NULL && $alias->getClass() === $service->getClass();
  }

  /**
   * Checks for #[Autowire] or a sibling such as #[AutowireIterator].
   */
  private function hasAutowireFamilyAttribute(Param $param): bool {
    foreach ($this->attributes($param) as $attribute) {
      if (str_starts_with($attribute->name->toString(), self::AUTOWIRE_ATTRIBUTE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the service ID named by a parameter's #[Autowire], if literal.
   */
  private function attributeService(Param $param): ?string {
    foreach ($this->attributes($param) as $attribute) {
      if ($attribute->name->toString() !== self::AUTOWIRE_ATTRIBUTE) {
        continue;
      }
      foreach ($attribute->args as $index => $arg) {
        if (!$arg->value instanceof String_) {
          continue;
        }
        $argName = $arg->name?->toString();
        if ($argName === 'service') {
          return ltrim($arg->value->value, '@');
        }
        if (($argName === 'value' || ($argName === NULL && $index === 0)) && str_starts_with($arg->value->value, '@')) {
          return substr($arg->value->value, 1);
        }
      }
    }
    return NULL;
  }

  /**
   * Returns a parameter's attributes.
   *
   * @return list<\PhpParser\Node\Attribute>
   *   The flattened attribute list.
   */
  private function attributes(Param $param): array {
    $attributes = [];
    foreach ($param->attrGroups as $group) {
      array_push($attributes, ...$group->attrs);
    }
    return $attributes;
  }

  /**
   * Returns service IDs whose class satisfies a type.
   *
   * @return list<string>
   *   At most MAX_CANDIDATES service IDs.
   */
  private function candidates(string $type): array {
    if (!$this->reflectionProvider->hasClass($type)) {
      return [];
    }
    $target = new ObjectType($type);
    $candidates = [];
    foreach ($this->serviceMap->getServices() as $id => $definition) {
      $class = $definition->getClass();
      // Class-named IDs are aliases of the services listed under their own ID.
      if ($class === NULL || str_contains((string) $id, '\\') || !$this->reflectionProvider->hasClass($class)) {
        continue;
      }
      if ($target->isSuperTypeOf(new ObjectType($class))->yes()) {
        $candidates[] = (string) $id;
        if (count($candidates) === self::MAX_CANDIDATES) {
          break;
        }
      }
    }
    return $candidates;
  }

}
