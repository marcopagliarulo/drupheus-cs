<?php

declare(strict_types=1);

namespace DrupalCS\Sniffs\ControlStructures;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Allows the alternate control statement syntax only in PHP templates.
 */
final class AlternativeSyntaxSniff implements Sniff {

  /**
   * {@inheritdoc}
   */
  public function register(): array {
    return [T_ENDIF, T_ENDFOREACH, T_ENDFOR, T_ENDWHILE, T_ENDSWITCH, T_ENDDECLARE];
  }

  /**
   * {@inheritdoc}
   */
  public function process(File $phpcsFile, $stackPtr): void {
    if (str_ends_with($phpcsFile->getFilename(), '.tpl.php')) {
      return;
    }

    $keyword = strtolower($phpcsFile->getTokens()[$stackPtr]['content']);
    $phpcsFile->addError('Use braces instead of the alternate syntax ending in "%s"; it is only allowed in templates', $stackPtr, 'Found', [$keyword]);
  }

}
