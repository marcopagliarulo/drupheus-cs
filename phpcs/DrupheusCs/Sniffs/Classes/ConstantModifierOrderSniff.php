<?php

declare(strict_types=1);

namespace DrupheusCs\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Requires "final" before the visibility of class constants.
 *
 * PSR-2's sniffs already check the order on methods and properties.
 */
final class ConstantModifierOrderSniff implements Sniff {

  /**
   * {@inheritdoc}
   */
  public function register(): array {
    return [T_CONST];
  }

  /**
   * {@inheritdoc}
   */
  public function process(File $phpcsFile, $stackPtr): void {
    $tokens = $phpcsFile->getTokens();
    $final = NULL;
    $visibility = NULL;
    for ($i = $stackPtr - 1; $i >= 0; $i--) {
      $code = $tokens[$i]['code'];
      if (isset(Tokens::$emptyTokens[$code])) {
        continue;
      }
      if ($code === T_FINAL) {
        $final = $i;
      }
      elseif (isset(Tokens::$scopeModifiers[$code])) {
        $visibility = $i;
      }
      else {
        break;
      }
    }
    if ($final === NULL || $visibility === NULL || $final < $visibility) {
      return;
    }

    $fix = $phpcsFile->addFixableError('The final declaration must precede the visibility declaration', $final, 'FinalAfterVisibility');
    if ($fix) {
      $phpcsFile->fixer->beginChangeset();
      $phpcsFile->fixer->replaceToken($final, '');
      if ($tokens[$final + 1]['code'] === T_WHITESPACE) {
        $phpcsFile->fixer->replaceToken($final + 1, '');
      }
      $phpcsFile->fixer->addContentBefore($visibility, 'final ');
      $phpcsFile->fixer->endChangeset();
    }
  }

}
