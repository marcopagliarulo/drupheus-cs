<?php

declare(strict_types=1);

namespace DrupalCS\PHPStan\Rule;

use DrupalCS\PHPStan\Drupal\ExtensionLocator;
use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Requires filter placeholders such as [mymodule-tag] to name the module.
 *
 * Only strings inside filter plugins are checked. In a regular expression only
 * escaped brackets are placeholders, since [a-z] is a character class there.
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Scalar\String_>
 */
final class FilterPlaceholderRule implements Rule {

  private const string FILTER = 'Drupal\filter\Plugin\FilterInterface';

  /**
   * Constructs a FilterPlaceholderRule object.
   */
  public function __construct(
    private readonly ExtensionLocator $extensions,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getNodeType(): string {
    return String_::class;
  }

  /**
   * {@inheritdoc}
   */
  public function processNode(Node $node, Scope $scope): array {
    $class = $scope->getClassReflection();
    if ($class === NULL || !$class->implementsInterface(self::FILTER)) {
      return [];
    }
    $module = $this->extensions->extensionsFor($scope->getFile())[0]['name'] ?? NULL;
    if ($module === NULL) {
      return [];
    }

    $value = $node->value;
    $pattern = $this->isRegex($value) ? '/\\\\\[(\/?)([A-Za-z][\w-]*)\\\\\]/' : '/\[(\/?)([A-Za-z][\w-]*)\]/';
    preg_match_all($pattern, $value, $matches, PREG_SET_ORDER);

    $errors = [];
    foreach ($matches as [, $closing, $name]) {
      if ($this->isValid($name, $module, $closing === '/')) {
        continue;
      }
      $message = 'Placeholder [%1$s%2$s] must be prefixed with the module name and a hyphen or underscore, such as [%1$s%3$s-%2$s].';
      $errors[] = RuleErrorBuilder::message(sprintf($message, $closing, $name, $module))
        ->identifier('drupalCs.naming.placeholder')
        ->build();
    }
    return $errors;
  }

  /**
   * Checks whether a placeholder name is namespaced by the module.
   */
  private function isValid(string $name, string $module, bool $closing): bool {
    foreach (['-', '_'] as $separator) {
      if (str_starts_with($name, $module . $separator) || ($closing && str_ends_with($name, $separator . $module))) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks whether a string looks like a PCRE pattern.
   *
   * Brackets other than braces are not accepted as delimiters, since
   * "[tag]" is the placeholder itself far more often than a pattern.
   */
  private function isRegex(string $value): bool {
    if (strlen($value) < 3 || ctype_alnum($value[0]) || ctype_space($value[0]) || str_contains('\\[(<', $value[0])) {
      return FALSE;
    }
    $closing = $value[0] === '{' ? '}' : $value[0];
    return preg_match('/' . preg_quote($closing, '/') . '[imsxuADSUXJn]*$/', substr($value, 1)) === 1;
  }

}
