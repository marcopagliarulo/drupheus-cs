<?php

declare(strict_types=1);

namespace DrupalCS\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Requires "object" rather than "stdClass" in documented types.
 */
final class ObjectTypeSniff implements Sniff {

  /**
   * The tags whose first word is a type.
   */
  private const array TAGS = ['@param', '@return', '@var', '@property', '@property-read', '@property-write'];

  /**
   * Matches stdClass as a whole type name, with or without a backslash.
   */
  private const string PATTERN = '/(?<![\w\\\\])\\\\?stdClass(?![\w\\\\])/i';

  /**
   * {@inheritdoc}
   */
  public function register(): array {
    return [T_DOC_COMMENT_TAG];
  }

  /**
   * {@inheritdoc}
   */
  public function process(File $phpcsFile, $stackPtr): void {
    $tokens = $phpcsFile->getTokens();
    if (!in_array(strtolower($tokens[$stackPtr]['content']), self::TAGS, TRUE)) {
      return;
    }

    $string = $stackPtr + 2;
    if (($tokens[$string]['code'] ?? NULL) !== T_DOC_COMMENT_STRING || $tokens[$string]['line'] !== $tokens[$stackPtr]['line']) {
      return;
    }

    $content = $tokens[$string]['content'];
    $type = strtok($content, " \t");
    if ($type === FALSE || preg_match(self::PATTERN, $type) !== 1) {
      return;
    }

    $fix = $phpcsFile->addFixableError('Use "object" rather than "stdClass" in %s types', $string, 'StdClass', [$tokens[$stackPtr]['content']]);
    if ($fix) {
      $phpcsFile->fixer->replaceToken($string, preg_replace(self::PATTERN, 'object', $type) . substr($content, strlen($type)));
    }
  }

}
