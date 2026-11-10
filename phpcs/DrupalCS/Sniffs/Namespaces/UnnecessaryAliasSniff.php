<?php

declare(strict_types=1);

namespace DrupalCS\Sniffs\Namespaces;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Allows "use ... as" aliases only to avoid a name collision.
 *
 * A name collides with another import, with a class declared in the file, or
 * with a class of the same namespace, found as a sibling file under PSR-4.
 */
final class UnnecessaryAliasSniff implements Sniff {

  /**
   * Tokens that make up a class name.
   */
  private const array NAME_TOKENS = [
    T_STRING,
    T_NS_SEPARATOR,
    T_NAME_QUALIFIED,
    T_NAME_FULLY_QUALIFIED,
    T_NAME_RELATIVE,
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
    $imports = $this->imports($phpcsFile);
    $taken = $this->declaredNames($phpcsFile);
    foreach ($imports as $import) {
      $taken[strtolower($import['short'])][] = $import['pointer'];
      if ($import['alias'] !== NULL) {
        $taken[strtolower($import['alias'])][] = $import['pointer'];
      }
    }

    $directory = dirname($phpcsFile->getFilename());
    foreach ($imports as $import) {
      if ($import['alias'] === NULL) {
        continue;
      }
      $others = array_diff($taken[strtolower($import['short'])], [$import['pointer']]);
      if ($others !== [] || ($import['short'] !== $import['alias'] && is_file($directory . '/' . $import['short'] . '.php'))) {
        continue;
      }
      $message = 'Alias %s for %s is not needed: alias a class only to avoid a name collision';
      $phpcsFile->addError($message, $import['pointer'], 'Found', [$import['alias'], $import['name']]);
    }

    return $phpcsFile->numTokens;
  }

  /**
   * Returns the class imports of a file.
   *
   * @return list<array{name: string, short: string, alias: ?string, pointer: int}>
   *   The imports.
   */
  private function imports(File $phpcsFile): array {
    $tokens = $phpcsFile->getTokens();
    $imports = [];
    $use = 0;
    while (($use = $phpcsFile->findNext(T_USE, $use + 1)) !== FALSE) {
      // Skip trait imports and closure variables.
      $conditions = array_diff($tokens[$use]['conditions'], [T_NAMESPACE]);
      $next = $phpcsFile->findNext(Tokens::$emptyTokens, $use + 1, NULL, TRUE);
      if ($conditions !== [] || $next === FALSE || $tokens[$next]['code'] === T_OPEN_PARENTHESIS) {
        continue;
      }
      // Functions and constants have their own symbol tables.
      if (in_array(strtolower($tokens[$next]['content']), ['function', 'const'], TRUE)) {
        continue;
      }

      $end = $phpcsFile->findNext(T_SEMICOLON, $use + 1);
      if ($end === FALSE) {
        break;
      }
      $prefix = '';
      $name = '';
      $alias = NULL;
      $start = $next;
      for ($i = $next; $i <= $end; $i++) {
        $code = $tokens[$i]['code'];
        if (in_array($code, self::NAME_TOKENS, TRUE)) {
          if ($alias === '') {
            $alias = $tokens[$i]['content'];
          }
          else {
            $name .= $tokens[$i]['content'];
          }
        }
        elseif ($code === T_AS) {
          $alias = '';
        }
        elseif ($code === T_OPEN_USE_GROUP) {
          $prefix = $name;
          $name = '';
          $start = $i + 1;
        }
        elseif ($code === T_COMMA || $code === T_CLOSE_USE_GROUP || $code === T_SEMICOLON) {
          if ($name !== '') {
            $full = ltrim($prefix . $name, '\\');
            $parts = explode('\\', $full);
            $imports[] = ['name' => $full, 'short' => end($parts), 'alias' => $alias ?: NULL, 'pointer' => $start];
          }
          $name = '';
          $alias = NULL;
          $start = $phpcsFile->findNext(Tokens::$emptyTokens, $i + 1, $end, TRUE) ?: $i;
        }
      }
      $use = $end;
    }
    return $imports;
  }

  /**
   * Returns the classes, interfaces, traits and enums the file declares.
   *
   * @return array<string, list<int>>
   *   Pointers keyed by lowercase name.
   */
  private function declaredNames(File $phpcsFile): array {
    $names = [];
    $pointer = 0;
    while (($pointer = $phpcsFile->findNext([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], $pointer + 1)) !== FALSE) {
      $name = $phpcsFile->getDeclarationName($pointer);
      if (is_string($name) && $name !== '') {
        $names[strtolower($name)][] = $pointer;
      }
    }
    return $names;
  }

}
