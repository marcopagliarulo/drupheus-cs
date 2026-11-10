<?php

declare(strict_types=1);

namespace DrupheusCs\TwigCsFixer;

use TwigCsFixer\Rules\AbstractRule;
use TwigCsFixer\Token\Token;
use TwigCsFixer\Token\Tokens;

/**
 * Requires a {# /** @file ... *\/ #} docblock at the top of each template.
 */
final class TemplateDocBlockRule extends AbstractRule {

  /**
   * Tokens that end a Twig comment.
   */
  private const array END_TYPES = [
    Token::COMMENT_END_TYPE,
    Token::DOC_COMMENT_END_TYPE,
    Token::EOF_TYPE,
  ];

  /**
   * {@inheritdoc}
   */
  protected function process(int $tokenIndex, Tokens $tokens): void {
    if ($tokenIndex !== 0) {
      return;
    }
    $all = $tokens->toArray();
    // Help topics start with front matter, and single directory components
    // are documented in their component.yml file.
    $file = $all[0]->getFilename();
    if (str_contains($file, '/help_topics/') || glob(dirname($file) . '/*.component.yml') !== []) {
      return;
    }

    $index = 0;
    while (isset($all[$index]) && $all[$index]->isMatching(Token::INDENT_TOKENS + Token::EOL_TOKENS)) {
      $index++;
    }

    $comment = '';
    if (isset($all[$index]) && $all[$index]->isMatching([Token::COMMENT_START_TYPE, Token::DOC_COMMENT_START_TYPE])) {
      for (; isset($all[$index]) && !$all[$index]->isMatching(self::END_TYPES); $index++) {
        $comment .= $all[$index]->getValue();
      }
    }

    if (!str_contains($comment, '/**') || !str_contains($comment, '@file')) {
      $this->addFileError('The template must start with a docblock: {# /** @file ... */ #}.', $all[0]);
    }
  }

}
