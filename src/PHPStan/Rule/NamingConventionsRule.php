<?php

declare(strict_types=1);

namespace DrupheusCs\PHPStan\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces the class naming rules Coder does not cover.
 *
 * @implements \PHPStan\Rules\Rule<\PHPStan\Node\InClassNode>
 */
final class NamingConventionsRule implements Rule {

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
    if ($class->isAnonymous()) {
      return [];
    }

    $name = $class->getNativeReflection()->getShortName();
    $line = $node->getOriginalNode()->getStartLine();
    $errors = [];

    if ($class->implementsInterface(\Throwable::class) && !str_ends_with($name, 'Exception')) {
      $errors[] = $this->error($name . ' is an exception, so its name must end in "Exception".', 'exceptionSuffix', $line);
    }
    if (str_ends_with($name, 'Interface') && !$class->isInterface()) {
      $errors[] = $this->error($name . ' is not an interface, so its name must not end in "Interface".', 'interfaceSuffix', $line);
    }
    if (str_ends_with($name, 'Trait') && !$class->isTrait()) {
      $errors[] = $this->error($name . ' is not a trait, so its name must not end in "Trait".', 'traitSuffix', $line);
    }
    if (str_ends_with($name, 'Test') && !$this->isTest($class)) {
      $errors[] = $this->error($name . ' is not a test, so its name must not end in "Test".', 'testSuffix', $line);
    }
    if (preg_match('/(?:^|[a-z])(Drupal|Class)(?=[A-Z]|$)/', $name, $matches) === 1) {
      $errors[] = $this->error(sprintf('%s must not include "%s" in its name.', $name, $matches[1]), 'reservedWord', $line);
    }
    if (preg_match('/[A-Z]{3,}/', $name) === 1) {
      $errors[] = $this->error($name . ' must use UpperCamel for acronyms, such as XmlParser rather than XMLParser.', 'acronym', $line);
    }

    return $errors;
  }

  /**
   * Checks whether a class is a PHPUnit test.
   */
  private function isTest(ClassReflection $class): bool {
    return $class->is('PHPUnit\Framework\TestCase');
  }

  /**
   * Builds a naming error.
   */
  private function error(string $message, string $id, int $line): IdentifierRuleError {
    return RuleErrorBuilder::message($message)->identifier('drupheusCs.naming.' . $id)->line($line)->build();
  }

}
