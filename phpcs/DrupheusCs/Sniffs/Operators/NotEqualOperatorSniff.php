<?php

declare(strict_types=1);

namespace DrupheusCs\Sniffs\Operators;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Requires != instead of the <> operator.
 */
final class NotEqualOperatorSniff implements Sniff {

  /**
   * {@inheritdoc}
   */
  public function register(): array {
    return [T_IS_NOT_EQUAL];
  }

  /**
   * {@inheritdoc}
   */
  public function process(File $phpcsFile, $stackPtr): void {
    if ($phpcsFile->getTokens()[$stackPtr]['content'] !== '<>') {
      return;
    }

    $fix = $phpcsFile->addFixableError('Weak inequality must use the != operator, not <>', $stackPtr, 'Found');
    if ($fix) {
      $phpcsFile->fixer->replaceToken($stackPtr, '!=');
    }
  }

}
