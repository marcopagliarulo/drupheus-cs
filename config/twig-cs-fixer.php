<?php

/**
 * @file
 * Twig-CS-Fixer configuration: core's rules plus the template docblock rule.
 *
 * Core ships its configuration from Drupal 11.5. Older versions use a copy of
 * it. DRUPAL_CS_CORE_DIR points to the core directory.
 */

declare(strict_types=1);

use Drupal\Core\Template\TwigTransTokenParser;
use DrupalCS\TwigCsFixer\TemplateDocBlockRule;
use TwigCsFixer\Config\Config;
use TwigCsFixer\File\Finder;
use TwigCsFixer\Rules\Literal\CompactHashRule;
use TwigCsFixer\Rules\Whitespace\IndentRule;
use TwigCsFixer\Ruleset\Ruleset;
use TwigCsFixer\Standard\TwigCsFixer;

$core = getenv('DRUPAL_CS_CORE_DIR');
if (is_string($core) && is_file($core . '/.twig-cs-fixer.php')) {
  $config = require $core . '/.twig-cs-fixer.php';
}
else {
  // Matches core/.twig-cs-fixer.php from Drupal 11.5.
  $config = new Config();
  $config->addTokenParser(new TwigTransTokenParser());

  $finder = new Finder();
  $finder->exclude('tests');
  $config->setFinder($finder);

  $ruleset = new Ruleset();
  $ruleset->addStandard(new TwigCsFixer());
  $ruleset->overrideRule(new CompactHashRule(TRUE));
  $ruleset->overrideRule(new IndentRule(spaceRatio: 2));

  $config->allowNonFixableRules();
  $config->setRuleset($ruleset);
}

$config->getRuleset()->addRule(new TemplateDocBlockRule());

return $config;
