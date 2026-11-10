<?php

declare(strict_types=1);

namespace DrupheusCs\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Runs PHPCS and PHPStan over the fixture modules in a real Drupal site.
 *
 * Set DRUPHEUS_CS_SITE to a Drupal project with this package installed. The
 * fixture modules are copied to its web/modules/custom directory.
 */
final class FixturesTest extends TestCase {

  /**
   * The Drupal project path.
   */
  private string $site;

  /**
   * The last PHPStan messages, formatted as "file:message".
   *
   * @var list<string>
   */
  private array $messages = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $site = getenv('DRUPHEUS_CS_SITE');
    if ($site === FALSE || $site === '') {
      self::markTestSkipped('DRUPHEUS_CS_SITE is not set.');
    }
    $this->site = $site;
    $target = $this->site . '/web/modules/custom';
    shell_exec(sprintf('rm -rf %1$s/dcs_* && mkdir -p %1$s && cp -R %2$s/dcs_* %1$s/', escapeshellarg($target), escapeshellarg(dirname(__DIR__, 2) . '/fixtures')));
  }

  /**
   * Tests the sniffs added on top of Coder.
   */
  public function testPhpcs(): void {
    $report = json_decode((string) shell_exec(sprintf('cd %s && vendor/bin/phpcs --standard=DrupheusCs --report=json web/modules/custom/dcs_standard 2>/dev/null', escapeshellarg($this->site))), TRUE);
    $found = [];
    foreach ($report['files'] as $file => $data) {
      foreach ($data['messages'] as $message) {
        $found[] = basename($file) . ':' . $message['source'];
      }
    }

    foreach ([
      'dcs_standard.module:DrupheusCs.Operators.NotEqualOperator.Found',
      'dcs_standard.module:DrupheusCs.NamingConventions.VariableCaseConsistency.Mixed',
      'dcs_standard.module:SlevomatCodingStandard.ControlStructures.RequireShortTernaryOperator.RequiredShortTernaryOperator',
      'DrupalThing.php:Generic.Files.OneObjectStructurePerFile.MultipleFound',
      'DrupalThing.php:SlevomatCodingStandard.Classes.ForbiddenPublicProperty.ForbiddenPublicProperty',
      'DrupalThing.php:SlevomatCodingStandard.TypeHints.DeclareStrictTypes.DeclareStrictTypesMissing',
      'Aliases.php:DrupheusCs.ControlStructures.AlternativeSyntax.Found',
      'Aliases.php:DrupheusCs.Commenting.ObjectType.StdClass',
      'Aliases.php:Squiz.Strings.DoubleQuoteUsage.NotRequired',
      'dcs_standard.info.yml:DrupheusCs.Files.ProjectFileNames.ConfigName',
      'dcs_standard.info.yml:DrupheusCs.Files.ProjectFileNames.DocumentationName',
      'dcs_standard.info.yml:DrupheusCs.Files.ProjectFileNames.ComposerPackageName',
      'Modifiers.php:PSR2.Namespaces.UseDeclaration.MultipleDeclarations',
    ] as $expected) {
      self::assertContains($expected, $found);
    }

    // Html and the self-alias; the two Link imports collide.
    self::assertSame(2, count(array_keys($found, 'Aliases.php:DrupheusCs.Namespaces.UnnecessaryAlias.Found', TRUE)));
    // Only "plain": "It's fine" avoids escaping and the other interpolates.
    self::assertSame(1, count(array_keys($found, 'Aliases.php:Squiz.Strings.DoubleQuoteUsage.NotRequired', TRUE)));
    self::assertNotContains('Aliases.php:Squiz.Strings.DoubleQuoteUsage.ContainsVar', $found);
    // Only other_module.settings: views.view.* is a configuration entity.
    self::assertSame(1, count(array_keys($found, 'dcs_standard.info.yml:DrupheusCs.Files.ProjectFileNames.ConfigName', TRUE)));
    // Only dcs_standard.settings: dcs_standard.item.* covers the other one.
    self::assertSame(1, count(array_keys($found, 'dcs_standard.info.yml:DrupheusCs.Config.ConfigSchema.Missing', TRUE)));
    // Only LINKS: ORDERED declares final first.
    self::assertSame(1, count(array_keys($found, 'Modifiers.php:DrupheusCs.Classes.ConstantModifierOrder.FinalAfterVisibility', TRUE)));
  }

  /**
   * Tests the PHPStan rules.
   */
  public function testPhpstan(): void {
    $found = $this->phpstan();

    foreach ([
      'dcs_standard.module:drupheusCs.types.missingParameter',
      'dcs_standard.module:drupheusCs.types.missingReturn',
      'DrupalThing.php:drupheusCs.types.stdClass',
      'DrupalThing.php:drupheusCs.constructorPromotion.available',
      'Promotable.php:drupheusCs.constructorPromotion.available',
      'DrupalThing.php:drupheusCs.types.missingProperty',
      'DrupalThing.php:drupheusCs.naming.reservedWord',
      'BadError.php:drupheusCs.naming.exceptionSuffix',
      'WrongInterface.php:drupheusCs.naming.interfaceSuffix',
      'WrongTrait.php:drupheusCs.naming.traitSuffix',
      'XMLParser.php:drupheusCs.naming.acronym',
      'AnnotatedBlock.php:drupheusCs.pluginAttributes.annotation',
      'dcs_standard.module:drupheusCs.naming.variableCase',
      'ModernForm.php:drupheusCs.autowire.available',
      'ModernBlock.php:drupheusCs.autowire.available',
      'NeedsAttributeController.php:drupheusCs.autowire.available',
      'Explicit.php:drupheusCs.autowire.available',
      'BrokenAutowireController.php:drupheusCs.autowire.unresolvable',
      'Broken.php:drupheusCs.autowire.unresolvable',
      'ModernHooks.php:drupheusCs.autowire.unresolvable',
      'FloorForm.php:drupheusCs.autowire.available',
      'dcs_modern.module:drupheusCs.classHooks.procedural',
      'dcs_modern.module:drupheusCs.classHooks.legacyShim',
      'dcs_modern.install:drupheusCs.classHooks.requirements',
      'dcs_floor.module:drupheusCs.classHooks.procedural',
      'dcs_modern.module:method.deprecated',
      'WrongPlace.php:drupheusCs.naming.psr4',
      // Returning null from a non-nullable type.
      'dcs_standard.module:drupheusCs.types.nullable',
    ] as $expected) {
      self::assertContains($expected, $found);
    }

    foreach ([
      // Hook implementations keep the parameter names of the hook.
      'dcs_modern.module:drupheusCs.naming.variableCase',
      // Plugin autowiring needs a newer core than the module supports.
      'FloorBlock.php:drupheusCs.autowire.available',
      // Autowiring an optional scalar is valid in a service definition.
      'Fine.php:drupheusCs.autowire.unresolvable',
      // The parameter is reassigned before the property is set.
      'Reassigned.php:drupheusCs.constructorPromotion.available',
      // Report-only finding.
      'SetterController.php:drupheusCs.autowire.unavailable',
      'LocatedTest.php:drupheusCs.naming.psr4',
      'Aliases.php:drupheusCs.naming.psr4',
    ] as $unexpected) {
      self::assertNotContains($unexpected, $found);
    }

    // Only $bad_local and $new_value: $form_state is inherited.
    self::assertSame(2, count(array_keys($found, 'VariableNames.php:drupheusCs.naming.variableCase', TRUE)));

    // "last_run" is not prefixed and "Dcs_Standard_Mixed" is not lowercase.
    self::assertSame(2, count(array_keys($found, 'StateUser.php:drupheusCs.naming.stateKey', TRUE)));
    self::assertSame(1, count(array_keys($found, 'StateUser.php:drupheusCs.naming.requestAttribute', TRUE)));
    // [break], [quote] and [/quote]; [a-z] is a character class.
    self::assertSame(3, count(array_keys($found, 'PlaceholderFilter.php:drupheusCs.naming.placeholder', TRUE)));

    // The closure's return value does not count.
    self::assertContains('dcs_standard.module:dcs_standard_nothing() returns nothing, so it must declare a void return type.', $this->messages);
    self::assertContains('dcs_standard.module:dcs_standard_untyped() has no native return type hint.', $this->messages);

    self::assertSame(1, count(array_keys($found, 'dcs_floor.module:drupheusCs.classHooks.procedural', TRUE)), 'Preprocess hooks stay procedural below 11.2.');
    self::assertSame(2, count(array_keys($found, 'dcs_modern.module:drupheusCs.classHooks.procedural', TRUE)));
  }

  /**
   * Tests Twig-CS-Fixer, which needs no Node packages.
   */
  public function testTwig(): void {
    [$code, $output] = $this->languageTool('twig');
    self::assertSame(1, $code, $output);
    self::assertStringContainsString('bad.html.twig', $output);
    self::assertStringContainsString('The template must start with a docblock', $output);
    self::assertStringNotContainsString('documented.html.twig', $output);
  }

  /**
   * Tests the checks that run on Node.
   */
  public function testNodeTools(): void {
    if (!is_dir($this->site . '/web/core/node_modules/.bin')) {
      [$code, $output] = $this->languageTool('eslint');
      self::assertSame(0, $code);
      self::assertStringContainsString('Skipped eslint', $output);
      return;
    }

    foreach ([
      'eslint' => 'bad.js',
      'stylelint' => 'bad.css',
      'cspell' => 'mispeled',
    ] as $tool => $expected) {
      [$code, $output] = $this->languageTool($tool);
      self::assertNotSame(0, $code, $tool . ': ' . $output);
      self::assertStringContainsString($expected, $output, $tool);
    }

    [, $output] = $this->languageTool('eslint');
    self::assertStringContainsString('dcs_assets.settings.yml', $output);
  }

  /**
   * Tests enforcing snake_case instead of the default style.
   */
  public function testSnakeCaseVariables(): void {
    $found = $this->phpstan("\tdrupheusCs:\n\t\tvariableCase:\n\t\t\tstyle: snake_case\n");
    // Only $camelLocal: $bad_local and $new_value are now valid.
    self::assertSame(1, count(array_keys($found, 'VariableNames.php:drupheusCs.naming.variableCase', TRUE)));
  }

  /**
   * Tests that the variable case rule can be disabled.
   */
  public function testVariableCaseCanBeDisabled(): void {
    $found = $this->phpstan("\tdrupheusCs:\n\t\textras:\n\t\t\tvariableCase: false\n");
    self::assertNotContains('VariableNames.php:drupheusCs.naming.variableCase', $found);
  }

  /**
   * Runs one language tool on the assets fixture.
   *
   * @return array{int, string}
   *   The exit code and the output.
   */
  private function languageTool(string $tool): array {
    $config = $this->site . '/phpcs.xml';
    file_put_contents($config, '<?xml version="1.0"?><ruleset name="t"><file>web/modules/custom/dcs_assets</file></ruleset>');
    exec(sprintf('cd %s && vendor/bin/drupheus-cs check %s 2>&1', escapeshellarg($this->site), escapeshellarg($tool)), $lines, $code);
    unlink($config);
    return [$code, implode("\n", $lines)];
  }

  /**
   * Runs PHPStan on the fixture modules and returns the findings.
   *
   * @param string $parameters
   *   (optional) Extra lines for the parameters section.
   *
   * @return list<string>
   *   Entries formatted as "file:identifier".
   */
  private function phpstan(string $parameters = ''): array {
    $config = tempnam(sys_get_temp_dir(), 'drupheus-cs') . '.neon';
    file_put_contents($config, "parameters:\n\tlevel: 6\n\tpaths:\n\t\t- {$this->site}/web/modules/custom\n{$parameters}");
    $report = json_decode((string) shell_exec(sprintf('cd %s && vendor/bin/phpstan analyse --no-progress --memory-limit=-1 --error-format=json --configuration=%s 2>/dev/null', escapeshellarg($this->site), escapeshellarg($config))), TRUE);
    unlink($config);

    $found = [];
    foreach ($report['files'] as $file => $data) {
      foreach ($data['messages'] as $message) {
        $found[] = basename($file) . ':' . ($message['identifier'] ?? '');
        $this->messages[] = basename($file) . ':' . $message['message'];
      }
    }
    return $found;
  }

}
