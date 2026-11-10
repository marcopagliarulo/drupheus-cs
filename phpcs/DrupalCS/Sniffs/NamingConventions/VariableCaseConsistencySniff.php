<?php

declare(strict_types=1);

namespace DrupalCS\Sniffs\NamingConventions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Warns when a file mixes $camelCase and $snake_case local variables.
 */
final class VariableCaseConsistencySniff implements Sniff {

  /**
   * Tokens marking a constructor parameter as a promoted property.
   */
  private const array PROMOTION_MODIFIERS = [
    T_PUBLIC => TRUE,
    T_PROTECTED => TRUE,
    T_PRIVATE => TRUE,
    T_READONLY => TRUE,
  ];

  /**
   * {@inheritdoc}
   */
  public function register(): array {
    return [T_OPEN_TAG];
  }

  /**
   * {@inheritdoc}
   */
  public function process(File $phpcsFile, $stackPtr): int {
    $tokens = $phpcsFile->getTokens();
    $seen = [];

    // Parameter names are often imposed by a parent method or a hook.
    $parameters = [];
    foreach ($tokens as $ptr => $token) {
      if (in_array($token['code'], [T_FUNCTION, T_CLOSURE, T_FN], TRUE)) {
        foreach ($phpcsFile->getMethodParameters($ptr) as $parameter) {
          $parameters[$parameter['name']] = TRUE;
        }
      }
    }

    foreach ($tokens as $ptr => $token) {
      if ($token['code'] !== T_VARIABLE || isset($parameters[$token['content']]) || $this->isPromotedParameter($phpcsFile, $ptr)) {
        continue;
      }

      $style = $this->style(substr($token['content'], 1));
      if ($style === NULL) {
        continue;
      }

      $seen[$style] ??= $token['content'];
      $other = $style === 'camelCase' ? 'snake_case' : 'camelCase';
      if (isset($seen[$other])) {
        $phpcsFile->addWarning(
          'Do not mix camelCase and snake_case variables in a file: found %s after %s',
          $ptr,
          'Mixed',
          [$token['content'], $seen[$other]],
        );
        break;
      }
    }

    return $phpcsFile->numTokens;
  }

  /**
   * Returns the naming style of a variable, if it has a distinctive one.
   */
  private function style(string $name): ?string {
    if (str_starts_with($name, '_') || $name === strtoupper($name)) {
      return NULL;
    }
    if (str_contains($name, '_')) {
      return 'snake_case';
    }
    return preg_match('/[a-z][A-Z]/', $name) === 1 ? 'camelCase' : NULL;
  }

  /**
   * Checks whether a variable token declares a promoted property.
   *
   * Promoted properties follow the lowerCamel property rule, so they cannot
   * count against a file that uses snake_case local variables.
   */
  private function isPromotedParameter(File $phpcsFile, int $ptr): bool {
    $tokens = $phpcsFile->getTokens();
    for ($i = $ptr - 1; $i >= 0; $i--) {
      $code = $tokens[$i]['code'];
      if (isset(self::PROMOTION_MODIFIERS[$code])) {
        return TRUE;
      }
      if ($code === T_COMMA || $code === T_OPEN_PARENTHESIS || $code === T_SEMICOLON) {
        return FALSE;
      }
    }
    return FALSE;
  }

}
